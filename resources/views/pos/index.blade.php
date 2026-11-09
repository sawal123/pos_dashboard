<x-layouts::pos :business="$business" :outlets="$outlets" :active-outlet="$activeOutlet" :current-shift="$currentShift">
    {{-- Main POS Work Area --}}
    <div class="flex-1 flex flex-col lg:flex-row h-[calc(100vh-3.5rem)] md:h-[calc(100vh-4rem)] overflow-hidden">

        {{-- ==================== LEFT COLUMN: CATALOG ==================== --}}
        <div class="flex-1 flex flex-col min-w-0 bg-slate-50/70 dark:bg-slate-900/50 border-r border-slate-200 dark:border-slate-800">
            {{-- Catalog Search & Category Filters Bar --}}
            <div class="p-3 md:p-4 border-b border-slate-200 dark:border-slate-800 space-y-3 bg-white dark:bg-slate-900 shrink-0">
                <div class="flex items-center gap-2">
                    {{-- Search Input --}}
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input
                            type="text"
                            id="catalogSearchInput"
                            placeholder="Cari produk, SKU, barcode... (Tekan '/')"
                            autocomplete="off"
                            class="w-full pl-9 pr-14 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs md:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
                        />
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <button
                                type="button"
                                id="catalogSearchClearBtn"
                                class="hidden p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                aria-label="Bersihkan pencarian"
                            >
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                            <kbd class="px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-[10px] font-mono text-slate-400 bg-white dark:bg-slate-800 pointer-events-none">
                                /
                            </kbd>
                        </div>
                    </div>

                    @if($isGrosir ?? false)
                        {{-- Always-On Auto Scan Status Pill --}}
                        <div
                            id="posAutoScanStatusBadge"
                            class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-600 dark:text-emerald-400 text-[11px] font-semibold shrink-0 select-none"
                            title="Alat Scanner fisik (USB / Bluetooth) & input kode langsung aktif otomatis. Scan berulang otomatis menambah jumlah (N+1)."
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Alat Scanner Siap</span>
                        </div>

                        {{-- Scan Barcode Button for Grosir (Flexible Non-Blocking Camera Dock) --}}
                        <button
                            type="button"
                            id="btnOpenPosScanner"
                            onclick="window.openPosScanner()"
                            class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-xs md:text-sm shadow-sm transition-all shrink-0 cursor-pointer"
                            title="Buka Kamera Scan Fleksibel (Keranjang tetap terlihat & scan berulang otomatis N+1)"
                        >
                            <i data-lucide="scan" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">Kamera Scan</span>
                        </button>
                    @endif
                </div>

                @if($isGrosir ?? false)
                    {{-- Flexible Live Scan Feedback & Alat Scanner Bar (Shows clear Success/Error & N+1 Qty Counter) --}}
                    <div id="posLastScanResultBar" class="hidden rounded-xl border px-3 py-2 transition-all duration-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span id="posLastScanIcon" class="w-6 h-6 rounded-lg flex items-center justify-center font-black text-xs shrink-0">✓</span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span id="posLastScanTitle" class="font-extrabold text-slate-900 dark:text-white truncate">Produk Berhasil Discan</span>
                                    <span id="posLastScanQtyBadge" class="px-2 py-0.5 rounded-md bg-emerald-500 text-slate-950 font-mono font-black text-[11px]">
                                        Qty: 1
                                    </span>
                                </div>
                                <p id="posLastScanSub" class="text-[11px] text-slate-500 dark:text-slate-300 truncate">
                                    Scan produk yang sama lagi untuk menambah jumlah otomatis (N+1)
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 ml-auto shrink-0">
                            <button
                                type="button"
                                id="posLastScanMinusBtn"
                                onclick="window.adjustLastScannedQty(-1)"
                                class="px-2.5 py-1 rounded-lg bg-white/80 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                title="Kurangi 1"
                            >
                                -1
                            </button>
                            <button
                                type="button"
                                id="posLastScanPlusBtn"
                                onclick="window.adjustLastScannedQty(1)"
                                class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold shadow-xs transition-colors cursor-pointer"
                                title="Tambah +1 lagi (N+1)"
                            >
                                +1 Lagi
                            </button>
                            <button
                                type="button"
                                onclick="document.getElementById('posLastScanResultBar')?.classList.add('hidden')"
                                class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                                title="Sembunyikan status scan"
                            >
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Category Filter Pills --}}
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar text-xs">
                    <button
                        type="button"
                        class="category-filter-btn px-3 py-1.5 rounded-xl font-semibold transition-all whitespace-nowrap bg-indigo-600 text-white shadow-xs"
                        data-category-id="all"
                    >
                        Semua Menu ({{ count($products) }})
                    </button>
                    @foreach($categories as $category)
                        <button
                            type="button"
                            class="category-filter-btn px-3 py-1.5 rounded-xl font-semibold transition-all whitespace-nowrap bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 shadow-xs"
                            data-category-id="{{ $category->id }}"
                        >
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Product Grid Cards --}}
            <div id="productGridContainer" class="flex-1 overflow-y-auto p-3 md:p-4">
                <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 md:gap-3.5">
                    {{-- Dynamically rendered by JavaScript --}}
                </div>
                <div id="emptyCatalogState" class="hidden py-16 text-center text-slate-400 dark:text-slate-500">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <i data-lucide="package-search" class="w-6 h-6"></i>
                    </div>
                    <p class="font-bold text-sm text-slate-700 dark:text-slate-300">Tidak ada produk ditemukan</p>
                    <p class="text-xs mt-1">Coba gunakan kata kunci lain atau pilih kategori berbeda.</p>
                </div>
            </div>
        </div>

        {{-- ==================== RIGHT COLUMN: CART & CHECKOUT ==================== --}}
        <div class="w-full lg:w-[420px] xl:w-[460px] flex flex-col bg-white dark:bg-slate-900 shrink-0 border-t lg:border-t-0 border-slate-200 dark:border-slate-800 select-none">
            {{-- Cart Header & Customer Picker --}}
            <div class="p-3.5 border-b border-slate-100 dark:border-slate-800 space-y-2.5 bg-slate-50/50 dark:bg-slate-950/20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        <span class="font-bold text-sm text-slate-900 dark:text-white">Pesanan Kasir</span>
                    </div>
                    <button
                        type="button"
                        id="clearCartBtn"
                        class="text-xs text-rose-500 hover:text-rose-600 font-semibold flex items-center gap-1 transition-colors"
                        title="Kosongkan Keranjang"
                    >
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        <span>Kosongkan</span>
                    </button>
                </div>

                {{-- Customer Selector --}}
                <div class="relative">
                    <select
                        id="cartCustomerSelect"
                        class="w-full text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">-- Pelanggan Umum / Non-Member --</option>
                        @foreach($customers as $c)
                            <option value="{{ $c['id'] }}">
                                👤 {{ $c['name'] }} {{ $c['phone'] ? '('.$c['phone'].')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Cart Items List (Scrollable) --}}
            <div id="cartItemsList" class="flex-1 overflow-y-auto p-3 space-y-2 text-xs">
                {{-- Dynamically populated via JS --}}
            </div>

            {{-- Empty Cart Placeholder --}}
            <div id="emptyCartPlaceholder" class="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-400">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50/60 dark:bg-slate-800 flex items-center justify-center mb-2.5 text-indigo-400 dark:text-indigo-400">
                    <i data-lucide="shopping-cart" class="w-7 h-7"></i>
                </div>
                <p class="font-bold text-sm text-slate-700 dark:text-slate-300">Keranjang Masih Kosong</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 max-w-[200px]">
                    Klik produk di sebelah kiri untuk menambahkan ke pesanan.
                </p>
            </div>

            {{-- Promo & Notes (Collapsible or compact) --}}
            <div class="px-3.5 py-2.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-950/30 space-y-2 text-xs">
                {{-- Promo Selector --}}
                <div class="flex items-center gap-2">
                    <i data-lucide="badge-percent" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                    <select
                        id="cartPromoSelect"
                        class="flex-1 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">-- Tanpa Promo / Diskon --</option>
                        @foreach($promos as $promo)
                            <option value="{{ $promo['id'] }}" data-name="{{ $promo['name'] }}">
                                🏷️ {{ $promo['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Order Note --}}
                <div class="flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <input
                        type="text"
                        id="cartNoteInput"
                        placeholder="Catatan pesanan (opsional)..."
                        class="flex-1 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            {{-- Financial Summary & Checkout Action --}}
            <div class="p-3.5 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                <div class="space-y-1.5 text-xs">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span>Subtotal (<span id="cartItemsCount">0</span> item)</span>
                        <span id="cartSubtotalText" class="font-semibold text-slate-800 dark:text-slate-200">Rp 0</span>
                    </div>
                    <div id="cartDiscountRow" class="hidden flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                        <span class="flex items-center gap-1">
                            <span>Diskon</span>
                            <span id="cartDiscountLabel" class="text-[10px] font-mono px-1 py-0.2 rounded bg-emerald-100 dark:bg-emerald-950/60 font-bold"></span>
                        </span>
                        <span id="cartDiscountText" class="font-semibold">- Rp 0</span>
                    </div>
                    <div id="cartTaxRow" class="hidden flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span>Pajak (<span id="cartTaxRateText">11</span>%)</span>
                        <span id="cartTaxText" class="font-semibold text-slate-800 dark:text-slate-200">+ Rp 0</span>
                    </div>
                    <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="font-bold text-sm text-slate-900 dark:text-white">Total Bayar</span>
                        <span id="cartTotalText" class="font-black text-xl text-indigo-600 dark:text-indigo-400">Rp 0</span>
                    </div>
                </div>

                {{-- Checkout Trigger Button --}}
                <button
                    type="button"
                    id="checkoutBtn"
                    disabled
                    onclick="openPaymentModal()"
                    class="w-full py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 disabled:pointer-events-none text-white text-sm font-bold shadow-lg shadow-indigo-500/25 transition-all flex items-center justify-center gap-2"
                >
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    <span>Bayar Sekarang (F2)</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== VARIANT SELECTOR MODAL ==================== --}}
    <div id="variantSelectorModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-3 md:p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[92vh] animate-scale-in">
            {{-- Modal Header --}}
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/30">
                <div class="min-w-0 flex-1 pr-2">
                    <h3 id="variantModalProductName" class="font-bold text-slate-900 dark:text-white text-base truncate">
                        Pilih Varian
                    </h3>
                    <p id="variantModalBasePrice" class="text-xs text-slate-500 font-semibold">
                        Rp 0
                    </p>
                </div>
                <button
                    type="button"
                    onclick="closeVariantModal()"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 flex items-center justify-center transition-colors"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Groups and Options List --}}
            <div id="variantModalGroupsContainer" class="p-4 overflow-y-auto space-y-4 max-h-[60vh]">
                {{-- Dynamically populated --}}
            </div>

            {{-- Modal Footer --}}
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30 flex items-center justify-between gap-3">
                <div>
                    <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Item</span>
                    <span id="variantModalTotalPrice" class="font-black text-base text-indigo-600 dark:text-indigo-400 tabular-nums">
                        Rp 0
                    </span>
                </div>
                <button
                    type="button"
                    id="addVariantToCartBtn"
                    onclick="confirmVariantSelection()"
                    class="px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 flex items-center gap-2 transition"
                >
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    <span>Tambahkan</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== PAYMENT MODAL ==================== --}}
    <div id="paymentModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-3 md:p-4">
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[92vh] animate-scale-in">
            {{-- Modal Header --}}
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="wallet" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Pembayaran Pesanan</h3>
                        <p class="text-[11px] text-slate-500">Pilih metode pembayaran dan nominal</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="closePaymentModal()"
                    class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-4 space-y-4 overflow-y-auto flex-1">
                {{-- Total Tagihan Display --}}
                <div class="p-4 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/40 text-center">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Yang Harus Dibayar</span>
                    <div id="modalTotalPayText" class="font-black text-3xl text-indigo-600 dark:text-indigo-400 mt-0.5">
                        Rp 0
                    </div>
                </div>

                {{-- Payment Method Selector --}}
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Metode Pembayaran</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button
                            type="button"
                            class="payment-method-btn p-3 rounded-xl border-2 border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold text-xs flex flex-col items-center gap-1.5 transition-all"
                            data-method="cash"
                        >
                            <i data-lucide="banknote" class="w-5 h-5"></i>
                            <span>Tunai</span>
                        </button>
                        <button
                            type="button"
                            class="payment-method-btn p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 font-bold text-xs flex flex-col items-center gap-1.5 transition-all"
                            data-method="qris"
                        >
                            <i data-lucide="qr-code" class="w-5 h-5"></i>
                            <span>QRIS</span>
                        </button>
                        <button
                            type="button"
                            class="payment-method-btn p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 font-bold text-xs flex flex-col items-center gap-1.5 transition-all"
                            data-method="transfer"
                        >
                            <i data-lucide="landmark" class="w-5 h-5"></i>
                            <span>Transfer</span>
                        </button>
                        <button
                            type="button"
                            class="payment-method-btn p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 font-bold text-xs flex flex-col items-center gap-1.5 transition-all"
                            data-method="debit"
                        >
                            <i data-lucide="credit-card" class="w-5 h-5"></i>
                            <span>Debit</span>
                        </button>
                    </div>
                </div>

                {{-- Cash Section (Shown only when method is cash) --}}
                <div id="cashInputSection" class="space-y-3 pt-2">
                    <div class="space-y-1">
                        <label for="cashReceivedInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Uang Tunai Diterima (Rp)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-sm">Rp</span>
                            <input
                                type="number"
                                id="cashReceivedInput"
                                min="0"
                                step="1000"
                                class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-base font-black text-slate-800 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    {{-- Quick Cash Buttons --}}
                    <div class="space-y-1">
                        <span class="text-[11px] font-semibold text-slate-400">Nominal Cepat:</span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button
                                type="button"
                                id="exactCashBtn"
                                class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-bold text-xs transition-colors"
                            >
                                Uang Pas
                            </button>
                            <button type="button" class="quick-cash-btn px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs" data-val="10000">10rb</button>
                            <button type="button" class="quick-cash-btn px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs" data-val="20000">20rb</button>
                            <button type="button" class="quick-cash-btn px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs" data-val="50000">50rb</button>
                            <button type="button" class="quick-cash-btn px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs" data-val="100000">100rb</button>
                            <button type="button" class="quick-cash-btn px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs" data-val="200000">200rb</button>
                        </div>
                    </div>

                    {{-- Kembalian Display --}}
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kembalian</span>
                        <span id="cashChangeText" class="font-black text-lg text-emerald-600 dark:text-emerald-400">Rp 0</span>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30 flex items-center gap-2">
                <button
                    type="button"
                    onclick="closePaymentModal()"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    id="submitCheckoutBtn"
                    onclick="executeCheckout()"
                    class="flex-[2] py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-md shadow-indigo-500/25 transition-all flex items-center justify-center gap-1.5"
                >
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Selesaikan Transaksi</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== SUCCESS / RECEIPT MODAL ==================== --}}
    <div id="successModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-3 md:p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[92vh] animate-scale-in">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 text-center relative bg-emerald-50/60 dark:bg-emerald-950/30">
                <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <h3 class="font-black text-base text-slate-900 dark:text-white">Transaksi Berhasil!</h3>
                <p id="successTxNumber" class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">-</p>
            </div>

            {{-- Printable Struk Area --}}
            <div id="thermalReceiptArea" class="p-4 space-y-3 overflow-y-auto flex-1 font-mono text-[11px] text-slate-700 dark:text-slate-300">
                <div class="text-center border-b border-dashed border-slate-200 dark:border-slate-700 pb-2">
                    <div class="font-bold text-sm text-slate-900 dark:text-white">{{ $business->name }}</div>
                    <div class="text-[10px] text-slate-500">{{ $activeOutlet->name }}</div>
                    <div id="receiptDateTime" class="text-[10px] text-slate-400 mt-1">-</div>
                </div>

                <div id="receiptItemsList" class="space-y-1.5 border-b border-dashed border-slate-200 dark:border-slate-700 pb-2">
                    {{-- Dynamically populated --}}
                </div>

                <div class="space-y-1 border-b border-dashed border-slate-200 dark:border-slate-700 pb-2 text-right">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span id="receiptSubtotal">-</span>
                    </div>
                    <div id="receiptDiscountRow" class="flex justify-between text-emerald-600 dark:text-emerald-400 hidden">
                        <span>Diskon:</span>
                        <span id="receiptDiscount">-</span>
                    </div>
                    <div id="receiptTaxRow" class="flex justify-between text-slate-500 hidden">
                        <span>Pajak (<span id="receiptTaxRate">11</span>%):</span>
                        <span id="receiptTax">-</span>
                    </div>
                    <div class="flex justify-between font-bold text-xs text-slate-900 dark:text-white pt-1">
                        <span>Total:</span>
                        <span id="receiptTotal">-</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Bayar (<span id="receiptMethod">Tunai</span>):</span>
                        <span id="receiptPaid">-</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Kembalian:</span>
                        <span id="receiptChange">-</span>
                    </div>
                </div>

                <div class="text-center text-[10px] text-slate-400 pt-1">
                    <p>Terima kasih atas kunjungan Anda!</p>
                    <p>Powered by NexaPOS Cloud</p>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30 flex items-center gap-2">
                <button
                    type="button"
                    onclick="printReceipt()"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center justify-center gap-1.5"
                >
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Cetak Struk</span>
                </button>
                <button
                    type="button"
                    onclick="closeSuccessModal()"
                    class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-md shadow-indigo-500/25 transition-all flex items-center justify-center gap-1.5"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Transaksi Baru</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== OPEN SHIFT MODAL ==================== --}}
    <div id="openShiftModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-3 md:p-4">
        <div class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col animate-scale-in">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Buka Shift Kasir</h3>
                </div>
                <button type="button" onclick="closeOpenShiftModal()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="openShiftForm" onsubmit="submitOpenShift(event)" class="p-4 space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Outlet</label>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-semibold border border-slate-200 dark:border-slate-700">
                        {{ $activeOutlet->name }}
                    </div>
                </div>
                <div>
                    <label for="openingCashInput" class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Modal Kasir Awal (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-slate-400">Rp</span>
                        <input
                            type="number"
                            id="openingCashInput"
                            name="opening_cash"
                            value="0"
                            min="0"
                            step="1000"
                            required
                            class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Uang modal receh/pecahan yang ada di laci kasir saat buka toko.</p>
                </div>
                <div>
                    <label for="openShiftNotesInput" class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Catatan (Opsional)</label>
                    <input
                        type="text"
                        id="openShiftNotesInput"
                        name="notes"
                        placeholder="Contoh: Shift Pagi Kasir 1"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="pt-2 flex items-center gap-2">
                    <button
                        type="button"
                        onclick="closeOpenShiftModal()"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="openShiftSubmitBtn"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-md shadow-indigo-500/25"
                    >
                        Buka Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== CLOSE SHIFT MODAL ==================== --}}
    <div id="closeShiftModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-3 md:p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[92vh] animate-scale-in">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Tutup Shift Kasir</h3>
                        <p id="closeShiftSubtitle" class="text-[11px] text-slate-400">Shift #-</p>
                    </div>
                </div>
                <button type="button" onclick="closeCloseShiftModal()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="closeShiftForm" onsubmit="submitCloseShift(event)" class="p-4 space-y-3.5 text-xs overflow-y-auto">
                <input type="hidden" id="closeShiftIdInput" name="shift_id" value="" />

                {{-- Summary Breakdown --}}
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1.5">
                    <div class="flex justify-between text-slate-500">
                        <span>Modal Awal:</span>
                        <span id="csModalAwal" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Penjualan Tunai:</span>
                        <span id="csCashSales" class="font-semibold text-emerald-600 dark:text-emerald-400">-</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Penjualan Non-Tunai:</span>
                        <span id="csNonCashSales" class="font-semibold text-indigo-600 dark:text-indigo-400">-</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Pengeluaran Kas Toko:</span>
                        <span id="csCashExpenses" class="font-semibold text-rose-500">-</span>
                    </div>
                    <div class="pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between font-bold text-slate-900 dark:text-white">
                        <span>Perkiraan Uang Kas di Laci:</span>
                        <span id="csExpectedCash" class="font-black text-indigo-600 dark:text-indigo-400">-</span>
                    </div>
                </div>

                {{-- Closing Cash Input --}}
                <div>
                    <label for="closingCashInput" class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Uang Kas Aktual di Laci (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-slate-400">Rp</span>
                        <input
                            type="number"
                            id="closingCashInput"
                            name="closing_cash"
                            min="0"
                            step="1000"
                            required
                            oninput="calculateCashDifference()"
                            class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-bold text-base text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div id="cashDiffContainer" class="mt-1.5 p-2 rounded-xl text-xs flex items-center justify-between font-semibold hidden">
                        <span>Selisih:</span>
                        <span id="cashDiffValue">-</span>
                    </div>
                </div>

                <div>
                    <label for="closeShiftNotesInput" class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Catatan Penutupan</label>
                    <input
                        type="text"
                        id="closeShiftNotesInput"
                        name="notes"
                        placeholder="Contoh: Kas sesuai, laci rapi."
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <div class="pt-2 flex items-center gap-2">
                    <button
                        type="button"
                        onclick="closeCloseShiftModal()"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="closeShiftSubmitBtn"
                        class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md shadow-rose-500/25"
                    >
                        Tutup Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== CLIENT JAVASCRIPT ==================== --}}
    <script>
        // Server Data Injected Safely
        const POS_CONFIG = {
            businessId: {{ (int) $business->id }},
            outletId: {{ (int) $activeOutlet->id }},
            outletName: "{{ addslashes($activeOutlet->name) }}",
            hasOpenShift: {{ $currentShift ? 'true' : 'false' }},
            shiftId: {{ $currentShift ? (int) $currentShift->id : 'null' }},
            shiftNumber: "{{ $currentShift ? $currentShift->shift_number : '' }}",
            taxEnabled: {{ $business->tax_enabled ? 'true' : 'false' }},
            taxRate: {{ (float) ($business->tax_rate ?? 11.0) }},
            checkoutUrl: "{{ route('pos.checkout') }}",
            promoPreviewUrl: "{{ route('pos.promo-preview') }}",
            openShiftUrl: "{{ route('pos.shifts.open') }}",
            closeShiftUrl: "{{ route('pos.shifts.close') }}",
            shiftSummaryUrl: "{{ url('pos/shifts') }}",
            csrfToken: "{{ csrf_token() }}",
        };

        const ALL_PRODUCTS = @json($products);
        const ALL_PROMOS = @json($promos);

        function findProductByCode(rawCode) {
            if (!rawCode) return null;
            const code = String(rawCode).trim();
            if (!code) return null;
            const lowerCode = code.toLowerCase();

            return ALL_PRODUCTS.find(p => {
                const pBarcode = String(p.barcode || '').trim().toLowerCase();
                const pSku = String(p.sku || '').trim().toLowerCase();
                return (pBarcode && pBarcode === lowerCode) || (pSku && pSku === lowerCode);
            }) || null;
        }

        let lastScannedProductId = null;

        function updatePosScanResultBar({ success = true, title = '', qtyText = '', subtitle = '', productId = null } = {}) {
            const bar = document.getElementById('posLastScanResultBar');
            const icon = document.getElementById('posLastScanIcon');
            const titleEl = document.getElementById('posLastScanTitle');
            const qtyBadge = document.getElementById('posLastScanQtyBadge');
            const subEl = document.getElementById('posLastScanSub');
            const minusBtn = document.getElementById('posLastScanMinusBtn');
            const plusBtn = document.getElementById('posLastScanPlusBtn');

            if (!bar) return;
            lastScannedProductId = success ? productId : null;
            bar.classList.remove('hidden');

            if (success) {
                bar.className = 'rounded-xl border-2 border-emerald-500/60 bg-emerald-50/90 dark:bg-emerald-950/40 px-3 py-2 transition-all duration-150 flex flex-wrap items-center justify-between gap-2 text-xs shadow-xs scale-[1.01]';
                setTimeout(() => bar.classList.remove('scale-[1.01]'), 180);
                if (icon) {
                    icon.className = 'w-6 h-6 rounded-lg bg-emerald-500 text-slate-950 flex items-center justify-center font-black text-xs shrink-0';
                    icon.textContent = '✓';
                }
                if (qtyBadge) {
                    qtyBadge.classList.remove('hidden');
                    qtyBadge.textContent = qtyText || 'Qty: 1';
                }
                if (minusBtn) minusBtn.classList.toggle('hidden', !productId);
                if (plusBtn) plusBtn.classList.toggle('hidden', !productId);
            } else {
                bar.className = 'rounded-xl border-2 border-rose-500/60 bg-rose-50/90 dark:bg-rose-950/40 px-3 py-2 transition-all duration-150 flex flex-wrap items-center justify-between gap-2 text-xs shadow-xs';
                if (icon) {
                    icon.className = 'w-6 h-6 rounded-lg bg-rose-500 text-white flex items-center justify-center font-black text-xs shrink-0';
                    icon.textContent = '✕';
                }
                if (qtyBadge) qtyBadge.classList.add('hidden');
                if (minusBtn) minusBtn.classList.add('hidden');
                if (plusBtn) plusBtn.classList.add('hidden');
            }

            if (titleEl) titleEl.textContent = title;
            if (subEl) subEl.textContent = subtitle;
        }

        window.adjustLastScannedQty = function(delta) {
            if (!lastScannedProductId) return;
            const found = ALL_PRODUCTS.find(p => p.id === lastScannedProductId);
            if (!found) return;
            const lineKey = String(found.id);
            const existing = cart.find(item => (item.line_id || String(item.product_id)) === lineKey);
            if (delta > 0) {
                addToCart(found.id);
            } else if (existing) {
                updateCartQuantity(lineKey, delta);
            }
            const updated = cart.find(item => (item.line_id || String(item.product_id)) === lineKey);
            const currentQty = updated ? Number(updated.quantity || 0) : 0;
            const totalItems = cart.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);

            if (currentQty <= 0) {
                document.getElementById('posLastScanResultBar')?.classList.add('hidden');
                return;
            }

            updatePosScanResultBar({
                success: true,
                title: `${found.name}`,
                qtyText: `Total Produk Ini: ${currentQty} ${found.unit || 'pcs'} (${formatRp(currentQty * found.price)})`,
                subtitle: `Total seluruh keranjang: ${totalItems} item • Scan lagi untuk tambah (N+1)`,
                productId: found.id,
            });

            if (window.CameraScanner && window.CameraScanner.modal && !window.CameraScanner.modal.classList.contains('hidden')) {
                window.CameraScanner.updateContinuousFeedback(found.barcode || found.sku, {
                    found: true,
                    label: `✓ ${found.name} (Qty: ${currentQty})`,
                    subtitle: `Subtotal: ${formatRp(currentQty * found.price)} • Total Keranjang: ${totalItems} item`,
                    badgeText: `Qty: ${currentQty}x`,
                    popText: `BERHASIL (+1) • ${found.name} (Qty: ${currentQty})`,
                });
            }
        };

        function handleScannedCode(rawCode) {
            if (!rawCode) return { found: false };
            const code = String(rawCode).trim();
            if (!code) return { found: false };

            const found = findProductByCode(code);

            if (!found) {
                showToast('error', `Produk dengan kode "${code}" tidak ditemukan.`);
                const failMeta = {
                    found: false,
                    label: `Kode "${code}" tidak ditemukan`,
                    subtitle: 'Pastikan kode barcode / SKU sudah terdaftar pada produk',
                    badgeText: 'Tidak Ada',
                    closeScanner: false,
                };
                updatePosScanResultBar({
                    success: false,
                    title: `Kode "${code}" Tidak Ditemukan`,
                    subtitle: 'Periksa kembali barcode/SKU produk atau daftarkan di menu Produk.',
                });
                if (window.CameraScanner && window.CameraScanner.modal && !window.CameraScanner.modal.classList.contains('hidden')) {
                    window.CameraScanner.updateContinuousFeedback(code, failMeta);
                }
                return failMeta;
            }

            if (found.is_out_of_stock) {
                showToast('warning', `Stok produk "${found.name}" habis.`);
                const emptyMeta = {
                    found: false,
                    label: `Stok Habis: ${found.name}`,
                    subtitle: 'Produk tidak dapat ditambahkan ke keranjang',
                    badgeText: 'Stok 0',
                    closeScanner: false,
                };
                updatePosScanResultBar({
                    success: false,
                    title: `Stok Habis: ${found.name}`,
                    subtitle: 'Stok produk 0 sehingga tidak dapat ditambahkan ke keranjang.',
                });
                return emptyMeta;
            }

            // If product has variants, open variant modal and close camera scanner so user can pick variant
            if (found.has_variants) {
                openVariantModal(found);
                return {
                    found: true,
                    label: `Pilih Varian: ${found.name}`,
                    subtitle: 'Silakan pilih varian produk',
                    closeScanner: true,
                };
            } else {
                addToCart(found.id);
                const lineKey = String(found.id);
                const cartItem = cart.find(item => (item.line_id || String(item.product_id)) === lineKey);
                const itemQty = cartItem ? (Number(cartItem.quantity) || 1) : 1;
                const totalItems = cart.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);

                showToast('success', `"${found.name}" masuk keranjang (Qty: ${itemQty}).`);

                const successMeta = {
                    found: true,
                    label: `✓ ${found.name} (Qty: ${itemQty})`,
                    subtitle: `Subtotal: ${formatRp(itemQty * found.price)} • Total Keranjang: ${totalItems} item`,
                    badgeText: `Qty: ${itemQty}x`,
                    popText: `BERHASIL (+1) • ${found.name} (Qty: ${itemQty})`,
                    closeScanner: false,
                };

                updatePosScanResultBar({
                    success: true,
                    title: `✓ ${found.name}`,
                    qtyText: `Qty di Keranjang: ${itemQty} ${found.unit || 'pcs'} (${formatRp(itemQty * found.price)})`,
                    subtitle: `Kode: ${found.barcode || found.sku} • Total Keranjang: ${totalItems} item • Scan lagi untuk tambah (N+1)`,
                    productId: found.id,
                });

                if (window.CameraScanner && window.CameraScanner.modal && !window.CameraScanner.modal.classList.contains('hidden')) {
                    window.CameraScanner.lastRepeatableCode = code;
                    window.CameraScanner.updateContinuousFeedback(code, successMeta);
                }

                return successMeta;
            }
        }

        window.openPosScanner = function() {
            if (window.CameraScanner) {
                window.CameraScanner.open(
                    (code) => handleScannedCode(code),
                    { continuous: true, docked: true, allowContinuousToggle: true }
                );
            }
        };

        // State Management
        let cart = [];
        let selectedCategory = 'all';
        let searchQuery = '';
        let selectedPromoId = null;
        let appliedDiscount = 0;
        let selectedPaymentMethod = 'cash';
        let lastCompletedSale = null;
        let currentShiftExpectedCash = 0;

        // Number Formatter
        function formatRp(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }

        // Generate UUID for Idempotency
        function generateUUID() {
            if (crypto && crypto.randomUUID) {
                return crypto.randomUUID();
            }
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        // ==================== CATALOG RENDERING ====================
        function renderCatalog() {
            const grid = document.getElementById('productGrid');
            const empty = document.getElementById('emptyCatalogState');
            if (!grid) return;

            grid.innerHTML = '';
            const q = searchQuery.trim().toLowerCase();

            const filtered = ALL_PRODUCTS.filter(p => {
                // Category match
                if (selectedCategory !== 'all' && String(p.category_id) !== String(selectedCategory)) {
                    return false;
                }
                // Search match
                if (q) {
                    const matchName = p.name.toLowerCase().includes(q);
                    const matchSku = p.sku.toLowerCase().includes(q);
                    const matchBarcode = p.barcode ? p.barcode.toLowerCase().includes(q) : false;
                    return matchName || matchSku || matchBarcode;
                }
                return true;
            });

            if (filtered.length === 0) {
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');

            filtered.forEach(p => {
                const card = document.createElement('div');
                card.className = "p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-indigo-500/60 dark:hover:border-indigo-500/60 hover:shadow-md transition-all cursor-pointer flex flex-col justify-between group active:scale-[0.98]";
                card.onclick = () => {
                    if (p.has_variants) {
                        openVariantModal(p);
                    } else {
                        addToCart(p.id);
                    }
                };

                let variantBadge = '';
                if (p.has_variants) {
                    variantBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">Varian</span>`;
                }

                let stockBadge = '';
                if (p.kind === 'service') {
                    stockBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">Jasa</span>`;
                } else if (p.is_out_of_stock) {
                    stockBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">Habis</span>`;
                } else if (p.is_low_stock) {
                    stockBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">Sisa ${p.stock}</span>`;
                } else {
                    stockBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-500">Stok ${p.stock}</span>`;
                }

                let imageHtml = '';
                if (p.image_url) {
                    imageHtml = `<div class="w-full h-24 mb-2 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 shrink-0">
                        <img src="${p.image_url}" alt="${p.name}" class="w-full h-full object-cover">
                    </div>`;
                }

                card.innerHTML = `
                    <div>
                        ${imageHtml}
                        <div class="flex items-center justify-between gap-1 mb-1.5">
                            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 truncate max-w-[100px]">${p.category_name}</span>
                            <div class="flex items-center gap-1">${variantBadge}${stockBadge}</div>
                        </div>
                        <h4 class="font-bold text-xs md:text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 line-clamp-2 transition-colors">
                            ${p.name}
                        </h4>
                        <div class="text-[10px] font-mono text-slate-400 mt-0.5">${p.sku}</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <span class="font-black text-xs md:text-sm text-slate-900 dark:text-white">
                            ${formatRp(p.price)}
                        </span>
                        <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 group-hover:bg-indigo-600 text-indigo-600 dark:text-indigo-400 group-hover:text-white flex items-center justify-center transition-colors">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        </div>
                    </div>
                `;

                grid.appendChild(card);
            });

            if (typeof window.initIcons === 'function') window.initIcons();
        }

        // ==================== CART MANAGEMENT ====================
        function loadCartFromStorage() {
            try {
                const saved = localStorage.getItem('nexapos_cart_' + POS_CONFIG.outletId);
                if (saved) {
                    cart = JSON.parse(saved);
                }
            } catch (e) {
                cart = [];
            }
        }

        function saveCartToStorage() {
            try {
                localStorage.setItem('nexapos_cart_' + POS_CONFIG.outletId, JSON.stringify(cart));
            } catch (e) {}
        }

        // ==================== VARIANT SELECTOR MODAL ====================
        let currentVariantProduct = null;

        function openVariantModal(product) {
            currentVariantProduct = product;
            document.getElementById('variantModalProductName').textContent = product.name;
            document.getElementById('variantModalBasePrice').textContent = 'Harga dasar: ' + formatRp(product.price);

            const container = document.getElementById('variantModalGroupsContainer');
            container.innerHTML = '';

            (product.variant_groups || []).forEach(group => {
                const groupDiv = document.createElement('div');
                groupDiv.className = "p-3 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 space-y-2";

                const isSingle = group.selection_type === 'single';
                const requiredBadge = group.is_required
                    ? `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400">Wajib</span>`
                    : `<span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-500">Opsional</span>`;

                let limitText = '';
                if (!isSingle && group.max_selection) {
                    limitText = `<span class="text-[10px] text-slate-400">(Maks. ${group.max_selection})</span>`;
                }

                groupDiv.innerHTML = `
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-200/60 dark:border-slate-700/60">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-xs text-slate-900 dark:text-white">${group.name}</span>
                            ${limitText}
                        </div>
                        ${requiredBadge}
                    </div>
                    <div class="space-y-1.5 options-group-list" data-group-id="${group.id}" data-required="${group.is_required ? '1' : '0'}" data-type="${group.selection_type}" data-max="${group.max_selection || ''}">
                        ${(group.options || []).map((opt, idx) => {
                            const isChecked = opt.is_default || (isSingle && group.is_required && idx === 0);
                            const priceAdjText = opt.price_adjustment > 0 ? `+${formatRp(opt.price_adjustment)}` : '';

                            return `
                                <label class="flex items-center justify-between p-2 rounded-xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900 cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-600 transition text-xs">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="${isSingle ? 'radio' : 'checkbox'}"
                                            name="var_group_${group.id}"
                                            value="${opt.id}"
                                            data-price-adj="${opt.price_adjustment || 0}"
                                            data-opt-name="${opt.name}"
                                            data-group-name="${group.name}"
                                            data-ing-id="${opt.ingredient_product_id || ''}"
                                            data-ing-qty="${opt.ingredient_quantity || 0}"
                                            ${isChecked ? 'checked' : ''}
                                            onchange="handleVariantOptionChange(this)"
                                            class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 rounded ${isSingle ? '' : 'rounded-md'} border-slate-300 dark:border-slate-600"
                                        >
                                        <span class="font-medium text-slate-800 dark:text-slate-200">${opt.name}</span>
                                    </div>
                                    ${priceAdjText ? `<span class="font-bold text-[11px] text-indigo-600 dark:text-indigo-400 tabular-nums">${priceAdjText}</span>` : ''}
                                </label>
                            `;
                        }).join('')}
                    </div>
                `;

                container.appendChild(groupDiv);
            });

            updateVariantModalPrice();
            document.getElementById('variantSelectorModal').classList.remove('hidden');
            if (typeof window.initIcons === 'function') window.initIcons();
        }

        function closeVariantModal() {
            document.getElementById('variantSelectorModal').classList.add('hidden');
            currentVariantProduct = null;
        }

        function handleVariantOptionChange(input) {
            const groupList = input.closest('.options-group-list');
            if (!groupList) return;

            const isSingle = groupList.dataset.type === 'single';
            if (!isSingle) {
                const max = parseInt(groupList.dataset.max, 10);
                if (max > 0) {
                    const checkedBoxes = groupList.querySelectorAll('input[type="checkbox"]:checked');
                    if (checkedBoxes.length > max) {
                        input.checked = false;
                        showToast('warning', `Maksimal pilihan untuk varian ini adalah ${max}.`);
                    }
                }
            }

            updateVariantModalPrice();
        }

        function updateVariantModalPrice() {
            if (!currentVariantProduct) return;

            let totalAdj = 0;
            let canAdd = true;

            const groupLists = document.querySelectorAll('#variantModalGroupsContainer .options-group-list');
            groupLists.forEach(gl => {
                const isRequired = gl.dataset.required === '1';
                const checked = gl.querySelectorAll('input:checked');
                if (isRequired && checked.length === 0) {
                    canAdd = false;
                }
                checked.forEach(cb => {
                    totalAdj += parseInt(cb.dataset.priceAdj || '0', 10);
                });
            });

            const total = (currentVariantProduct.price || 0) + totalAdj;
            document.getElementById('variantModalTotalPrice').textContent = formatRp(total);

            const addBtn = document.getElementById('addVariantToCartBtn');
            if (addBtn) addBtn.disabled = !canAdd;
        }

        function confirmVariantSelection() {
            if (!currentVariantProduct) return;

            const selectedOptionIds = [];
            const variantsSnapshot = [];
            const summaryParts = [];
            let totalAdj = 0;

            const checkedInputs = document.querySelectorAll('#variantModalGroupsContainer input:checked');
            checkedInputs.forEach(cb => {
                const optId = parseInt(cb.value, 10);
                const priceAdj = parseInt(cb.dataset.priceAdj || '0', 10);
                const optName = cb.dataset.optName;
                const groupName = cb.dataset.groupName;
                const ingId = cb.dataset.ingId ? parseInt(cb.dataset.ingId, 10) : null;
                const ingQty = cb.dataset.ingQty ? parseFloat(cb.dataset.ingQty) : 0;

                selectedOptionIds.push(optId);
                totalAdj += priceAdj;

                let part = optName;
                if (priceAdj > 0) {
                    part += ` (+${formatRp(priceAdj)})`;
                }
                summaryParts.push(part);

                variantsSnapshot.push({
                    group_name: groupName,
                    option_id: optId,
                    option_name: optName,
                    price_adjustment: priceAdj,
                    ingredient_product_id: ingId,
                    ingredient_quantity: ingQty
                });
            });

            const variantSummary = summaryParts.join(', ');
            const unitPrice = currentVariantProduct.price + totalAdj;

            addToCartWithVariants(currentVariantProduct, selectedOptionIds, variantsSnapshot, variantSummary, unitPrice);
            closeVariantModal();
        }

        function addToCartWithVariants(product, optionIds, variantsSnapshot, variantSummary, unitPrice) {
            const lineKey = product.id + '::' + [...optionIds].sort((a,b)=>a-b).join('-');
            const existing = cart.find(item => (item.line_id || String(item.product_id)) === lineKey);

            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    line_id: lineKey,
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    unit_price: unitPrice,
                    base_price: product.price,
                    unit: product.unit,
                    kind: product.kind,
                    quantity: 1,
                    variant_option_ids: optionIds,
                    variants: variantsSnapshot,
                    variant_summary: variantSummary
                });
            }

            saveCartToStorage();
            renderCart();
            evaluateCartPromo();
        }

        function addToCart(productId) {
            const product = ALL_PRODUCTS.find(p => p.id === productId);
            if (!product) return;

            if (product.has_variants) {
                openVariantModal(product);
                return;
            }

            const lineKey = String(product.id);
            const existing = cart.find(item => (item.line_id || String(item.product_id)) === lineKey);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    line_id: lineKey,
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    unit_price: product.price,
                    base_price: product.price,
                    unit: product.unit,
                    kind: product.kind,
                    quantity: 1,
                    variant_option_ids: [],
                    variants: [],
                    variant_summary: null
                });
            }

            saveCartToStorage();
            renderCart();
            evaluateCartPromo();
        }

        function updateCartQuantity(lineId, delta) {
            const idx = cart.findIndex(item => (item.line_id || String(item.product_id)) === String(lineId));
            if (idx === -1) return;

            cart[idx].quantity += delta;
            if (cart[idx].quantity <= 0) {
                cart.splice(idx, 1);
            }

            saveCartToStorage();
            renderCart();
            evaluateCartPromo();
        }

        function removeFromCart(lineId) {
            cart = cart.filter(item => (item.line_id || String(item.product_id)) !== String(lineId));
            saveCartToStorage();
            renderCart();
            evaluateCartPromo();
        }

        function clearCart() {
            cart = [];
            appliedDiscount = 0;
            saveCartToStorage();
            renderCart();
        }

        function computeCartTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
            const discount = appliedDiscount || 0;
            const taxableSubtotal = Math.max(0, subtotal - discount);
            const tax = (POS_CONFIG.taxEnabled && POS_CONFIG.taxRate > 0)
                ? Math.round((taxableSubtotal * POS_CONFIG.taxRate) / 100)
                : 0;
            const total = taxableSubtotal + tax;
            return { subtotal, discount, taxableSubtotal, tax, total };
        }

        function renderCart() {
            const container = document.getElementById('cartItemsList');
            const placeholder = document.getElementById('emptyCartPlaceholder');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const itemsCountEl = document.getElementById('cartItemsCount');
            const subtotalTextEl = document.getElementById('cartSubtotalText');
            const totalTextEl = document.getElementById('cartTotalText');

            if (!container) return;

            if (cart.length === 0) {
                container.innerHTML = '';
                placeholder.classList.remove('hidden');
                checkoutBtn.disabled = true;
                itemsCountEl.textContent = '0';
                subtotalTextEl.textContent = formatRp(0);
                totalTextEl.textContent = formatRp(0);
                document.getElementById('cartDiscountRow').classList.add('hidden');
                document.getElementById('cartTaxRow').classList.add('hidden');
                return;
            }

            placeholder.classList.add('hidden');
            checkoutBtn.disabled = !POS_CONFIG.hasOpenShift;
            container.innerHTML = '';

            let totalItems = 0;

            cart.forEach(item => {
                const lineId = item.line_id || String(item.product_id);
                const lineTotal = item.unit_price * item.quantity;
                totalItems += item.quantity;

                const row = document.createElement('div');
                row.className = "p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/80 shadow-xs flex items-center justify-between gap-2";

                row.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <h5 class="font-bold text-slate-800 dark:text-slate-100 truncate">${item.name}</h5>
                        ${item.variant_summary ? `<div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium truncate">${item.variant_summary}</div>` : ''}
                        <div class="text-[11px] text-slate-400">
                            ${formatRp(item.unit_price)} / ${item.unit}
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            type="button"
                            onclick="updateCartQuantity('${lineId}', -1)"
                            class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center font-bold text-xs transition-colors"
                        >
                            -
                        </button>
                        <span class="w-8 text-center font-bold text-xs text-slate-900 dark:text-white">
                            ${item.quantity}
                        </span>
                        <button
                            type="button"
                            onclick="updateCartQuantity('${lineId}', 1)"
                            class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center font-bold text-xs transition-colors"
                        >
                            +
                        </button>
                    </div>

                    <div class="text-right shrink-0 min-w-[70px]">
                        <div class="font-bold text-slate-900 dark:text-white">${formatRp(lineTotal)}</div>
                        <button
                            type="button"
                            onclick="removeFromCart('${lineId}')"
                            class="text-[10px] text-rose-500 hover:underline"
                        >
                            Hapus
                        </button>
                    </div>
                `;

                container.appendChild(row);
            });

            const totals = computeCartTotals();
            itemsCountEl.textContent = totalItems.toString();
            subtotalTextEl.textContent = formatRp(totals.subtotal);
            totalTextEl.textContent = formatRp(totals.total);

            const discountRow = document.getElementById('cartDiscountRow');
            if (totals.discount > 0) {
                discountRow.classList.remove('hidden');
                document.getElementById('cartDiscountText').textContent = '- ' + formatRp(totals.discount);
            } else {
                discountRow.classList.add('hidden');
            }

            const taxRow = document.getElementById('cartTaxRow');
            if (POS_CONFIG.taxEnabled && totals.tax > 0) {
                taxRow.classList.remove('hidden');
                document.getElementById('cartTaxRateText').textContent = POS_CONFIG.taxRate;
                document.getElementById('cartTaxText').textContent = '+ ' + formatRp(totals.tax);
            } else {
                taxRow.classList.add('hidden');
            }
        }

        // ==================== PROMO PREVIEW ====================
        async function evaluateCartPromo() {
            const promoSelect = document.getElementById('cartPromoSelect');
            const promoId = promoSelect ? promoSelect.value : null;

            if (!promoId || cart.length === 0) {
                appliedDiscount = 0;
                renderCart();
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
            const lines = cart.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                line_total: item.unit_price * item.quantity
            }));

            const customerSelect = document.getElementById('cartCustomerSelect');
            const customerId = customerSelect && customerSelect.value ? Number(customerSelect.value) : null;

            try {
                const res = await fetch(POS_CONFIG.promoPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': POS_CONFIG.csrfToken
                    },
                    body: JSON.stringify({
                        promo_id: Number(promoId),
                        customer_id: customerId,
                        subtotal: subtotal,
                        lines: lines
                    })
                });

                const data = await res.json();
                if (data.eligible) {
                    appliedDiscount = data.discount;
                    document.getElementById('cartDiscountLabel').textContent = 'HEMAT ' + formatRp(data.discount);
                } else {
                    appliedDiscount = 0;
                    if (data.reason === 'promo_customer_required') {
                        showToast('info', 'Promo ini khusus untuk pelanggan terdaftar. Silakan pilih pelanggan di atas.');
                    } else if (data.reason === 'promo_customer_mismatch') {
                        showToast('info', 'Pelanggan yang dipilih tidak memenuhi syarat untuk promo khusus ini.');
                    } else if (data.reason === 'promo_min_purchase') {
                        showToast('info', 'Subtotal belum mencapai syarat minimal promo ini.');
                    } else if (data.reason === 'promo_target_mismatch') {
                        showToast('info', 'Item belanja tidak memenuhi target promo ini.');
                    }
                }
            } catch (e) {
                appliedDiscount = 0;
            }

            renderCart();
        }

        // ==================== PAYMENT MODAL LOGIC ====================
        function openPaymentModal() {
            if (!POS_CONFIG.hasOpenShift) {
                showToast('warning', 'Shift kasir belum dibuka. Silakan buka shift terlebih dahulu.');
                openOpenShiftModal();
                return;
            }

            if (cart.length === 0) {
                showToast('warning', 'Keranjang belanja masih kosong.');
                return;
            }

            const totals = computeCartTotals();

            document.getElementById('modalTotalPayText').textContent = formatRp(totals.total);
            document.getElementById('cashReceivedInput').value = totals.total;
            document.getElementById('cashChangeText').textContent = formatRp(0);

            // Default payment method = cash
            selectPaymentMethod('cash');

            document.getElementById('paymentModal').classList.remove('hidden');
            setTimeout(() => {
                const inp = document.getElementById('cashReceivedInput');
                if (inp) {
                    inp.focus();
                    inp.select();
                }
            }, 100);
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            document.querySelectorAll('.payment-method-btn').forEach(btn => {
                if (btn.getAttribute('data-method') === method) {
                    btn.classList.add('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-indigo-950/40', 'text-indigo-600', 'dark:text-indigo-400');
                    btn.classList.remove('border-slate-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
                } else {
                    btn.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-indigo-950/40', 'text-indigo-600', 'dark:text-indigo-400');
                    btn.classList.add('border-slate-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
                }
            });

            const cashSection = document.getElementById('cashInputSection');
            if (method === 'cash') {
                cashSection.classList.remove('hidden');
            } else {
                cashSection.classList.add('hidden');
            }
        }

        function calculateCashChange() {
            const totals = computeCartTotals();
            const received = Number(document.getElementById('cashReceivedInput').value || 0);

            const change = received - totals.total;
            const changeEl = document.getElementById('cashChangeText');

            if (change < 0) {
                changeEl.textContent = 'Kurang ' + formatRp(Math.abs(change));
                changeEl.classList.add('text-rose-500');
                changeEl.classList.remove('text-emerald-600', 'dark:text-emerald-400');
            } else {
                changeEl.textContent = formatRp(change);
                changeEl.classList.remove('text-rose-500');
                changeEl.classList.add('text-emerald-600', 'dark:text-emerald-400');
            }
        }

        // ==================== CHECKOUT EXECUTION ====================
        async function executeCheckout() {
            if (cart.length === 0) return;

            const totals = computeCartTotals();

            let cashReceived = totals.total;
            if (selectedPaymentMethod === 'cash') {
                cashReceived = Number(document.getElementById('cashReceivedInput').value || 0);
                if (cashReceived < totals.total) {
                    showToast('error', 'Uang tunai yang diterima kurang dari total belanja.');
                    return;
                }
            }

            const customerSelect = document.getElementById('cartCustomerSelect');
            const customerId = customerSelect && customerSelect.value ? Number(customerSelect.value) : null;
            const promoSelect = document.getElementById('cartPromoSelect');
            const promoId = promoSelect && promoSelect.value ? Number(promoSelect.value) : null;
            const noteInput = document.getElementById('cartNoteInput');
            const note = noteInput ? noteInput.value : '';

            const submitBtn = document.getElementById('submitCheckoutBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i data-lucide="loader" class="w-4 h-4 animate-spin"></i><span>Memproses...</span>`;
            if (typeof window.initIcons === 'function') window.initIcons();

            const clientSaleId = generateUUID();

            const payload = {
                client_sale_id: clientSaleId,
                outlet_id: POS_CONFIG.outletId,
                shift_id: POS_CONFIG.shiftId,
                customer_id: customerId,
                payment_method: selectedPaymentMethod,
                cash_received: cashReceived,
                promo_id: promoId,
                note: note,
                items: cart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    variant_option_ids: item.variant_option_ids || [],
                    variants: item.variants || []
                }))
            };

            try {
                const res = await fetch(POS_CONFIG.checkoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': POS_CONFIG.csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (!res.ok) {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Gagal memproses transaksi.');
                    showToast('error', errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `<i data-lucide="check" class="w-4 h-4"></i><span>Selesaikan Transaksi</span>`;
                    if (typeof window.initIcons === 'function') window.initIcons();
                    return;
                }

                // Success!
                lastCompletedSale = data.sale;
                closePaymentModal();
                showSuccessModal(data.sale);
                clearCart();
                showToast('success', 'Transaksi ' + data.sale.transaction_number + ' berhasil!');
            } catch (err) {
                showToast('error', 'Terjadi kesalahan jaringan atau server.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i data-lucide="check" class="w-4 h-4"></i><span>Selesaikan Transaksi</span>`;
                if (typeof window.initIcons === 'function') window.initIcons();
            }
        }

        // ==================== SUCCESS / RECEIPT MODAL ====================
        function showSuccessModal(sale) {
            document.getElementById('successTxNumber').textContent = '#' + sale.transaction_number;
            document.getElementById('receiptDateTime').textContent = sale.sold_at;
            document.getElementById('receiptSubtotal').textContent = formatRp(sale.subtotal);

            const discRow = document.getElementById('receiptDiscountRow');
            if (sale.discount_amount > 0) {
                discRow.classList.remove('hidden');
                document.getElementById('receiptDiscount').textContent = '- ' + formatRp(sale.discount_amount);
            } else {
                discRow.classList.add('hidden');
            }

            const taxRow = document.getElementById('receiptTaxRow');
            if (sale.tax_amount > 0) {
                taxRow.classList.remove('hidden');
                document.getElementById('receiptTaxRate').textContent = POS_CONFIG.taxRate;
                document.getElementById('receiptTax').textContent = '+ ' + formatRp(sale.tax_amount);
            } else {
                taxRow.classList.add('hidden');
            }

            document.getElementById('receiptTotal').textContent = formatRp(sale.total_amount);
            document.getElementById('receiptMethod').textContent = sale.payment_method.toUpperCase();
            document.getElementById('receiptPaid').textContent = formatRp(sale.cash_received);
            document.getElementById('receiptChange').textContent = formatRp(sale.change_amount);

            const itemsContainer = document.getElementById('receiptItemsList');
            itemsContainer.innerHTML = '';
            (sale.items || []).forEach(it => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'flex justify-between items-start';
                itemDiv.innerHTML = `
                    <div class="max-w-[190px]">
                        <div class="truncate font-medium text-slate-800 dark:text-slate-200">${it.name} x${it.quantity}</div>
                        ${it.variant_summary ? `<div class="text-[9px] text-slate-500 italic max-w-[190px] leading-tight">${it.variant_summary}</div>` : ''}
                    </div>
                    <div class="font-bold tabular-nums text-right">${formatRp(it.line_total)}</div>
                `;
                itemsContainer.appendChild(itemDiv);
            });

            document.getElementById('successModal').classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }

        function printReceipt() {
            window.print();
        }

        // ==================== SHIFT MODALS ====================
        function openOpenShiftModal() {
            document.getElementById('openShiftModal').classList.remove('hidden');
            setTimeout(() => {
                const inp = document.getElementById('openingCashInput');
                if (inp) {
                    inp.focus();
                    inp.select();
                }
            }, 100);
        }

        function closeOpenShiftModal() {
            document.getElementById('openShiftModal').classList.add('hidden');
        }

        async function submitOpenShift(e) {
            e.preventDefault();
            const btn = document.getElementById('openShiftSubmitBtn');
            btn.disabled = true;

            const form = document.getElementById('openShiftForm');
            const formData = new FormData(form);
            formData.append('outlet_id', POS_CONFIG.outletId);

            try {
                const res = await fetch(POS_CONFIG.openShiftUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': POS_CONFIG.csrfToken
                    },
                    body: formData
                });

                const data = await res.json();
                if (!res.ok) {
                    const msg = data.message || 'Gagal membuka shift.';
                    showToast('error', msg);
                    btn.disabled = false;
                    return;
                }

                showToast('success', data.message);
                setTimeout(() => window.location.reload(), 400);
            } catch (err) {
                showToast('error', 'Terjadi kesalahan membuka shift.');
                btn.disabled = false;
            }
        }

        async function openCloseShiftModal(shiftId) {
            document.getElementById('closeShiftIdInput').value = shiftId;
            document.getElementById('closeShiftModal').classList.remove('hidden');

            try {
                const res = await fetch(POS_CONFIG.shiftSummaryUrl + '/' + shiftId + '/summary', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('closeShiftSubtitle').textContent = 'Shift #' + data.shift.shift_number;
                    document.getElementById('csModalAwal').textContent = formatRp(data.summary.opening_cash);
                    document.getElementById('csCashSales').textContent = formatRp(data.summary.cash_sales);
                    document.getElementById('csNonCashSales').textContent = formatRp(data.summary.non_cash_sales);
                    document.getElementById('csCashExpenses').textContent = formatRp(data.summary.cash_expenses);
                    document.getElementById('csExpectedCash').textContent = formatRp(data.summary.expected_cash);

                    currentShiftExpectedCash = data.summary.expected_cash;
                    document.getElementById('closingCashInput').value = data.summary.expected_cash;
                    calculateCashDifference();
                }
            } catch (e) {
                showToast('error', 'Gagal memuat ringkasan shift.');
            }
        }

        function closeCloseShiftModal() {
            document.getElementById('closeShiftModal').classList.add('hidden');
        }

        function calculateCashDifference() {
            const closingCash = Number(document.getElementById('closingCashInput').value || 0);
            const diff = closingCash - currentShiftExpectedCash;
            const container = document.getElementById('cashDiffContainer');
            const valEl = document.getElementById('cashDiffValue');

            container.classList.remove('hidden');
            if (diff === 0) {
                container.className = 'mt-1.5 p-2 rounded-xl text-xs flex items-center justify-between font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
                valEl.textContent = 'Pas (Sesuai)';
            } else if (diff > 0) {
                container.className = 'mt-1.5 p-2 rounded-xl text-xs flex items-center justify-between font-semibold bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800';
                valEl.textContent = 'Lebih +' + formatRp(diff);
            } else {
                container.className = 'mt-1.5 p-2 rounded-xl text-xs flex items-center justify-between font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800';
                valEl.textContent = 'Kurang ' + formatRp(Math.abs(diff));
            }
        }

        async function submitCloseShift(e) {
            e.preventDefault();
            const btn = document.getElementById('closeShiftSubmitBtn');
            btn.disabled = true;

            const form = document.getElementById('closeShiftForm');
            const formData = new FormData(form);

            try {
                const res = await fetch(POS_CONFIG.closeShiftUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': POS_CONFIG.csrfToken
                    },
                    body: formData
                });

                const data = await res.json();
                if (!res.ok) {
                    const msg = data.message || 'Gagal menutup shift.';
                    showToast('error', msg);
                    btn.disabled = false;
                    return;
                }

                showToast('success', data.message);
                setTimeout(() => window.location.reload(), 400);
            } catch (err) {
                showToast('error', 'Terjadi kesalahan menutup shift.');
                btn.disabled = false;
            }
        }

        // ==================== EVENT LISTENERS & SHORTCUTS ====================
        function initPosPage() {
            const productGrid = document.getElementById('productGrid');
            if (!productGrid || productGrid.dataset.posInitialized === '1') return;
            productGrid.dataset.posInitialized = '1';

            loadCartFromStorage();
            renderCatalog();
            renderCart();

            // Search Input listener + Direct Barcode/SKU Auto-Scan (without clicking Scan button)
            const searchInput = document.getElementById('catalogSearchInput');
            const clearBtn = document.getElementById('catalogSearchClearBtn');
            let searchInputLastKeyTime = 0;
            let searchInputRapidStreak = 0;
            let searchInputAutoScanTimer = null;

            const clearSearchField = () => {
                if (!searchInput) return;
                searchInput.value = '';
                searchQuery = '';
                if (clearBtn) clearBtn.classList.add('hidden');
                renderCatalog();
            };

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    searchQuery = e.target.value;
                    if (clearBtn) clearBtn.classList.toggle('hidden', !searchQuery);
                    renderCatalog();

                    // If a USB/Bluetooth scanner injects characters rapidly without sending Enter, auto-detect exact barcode/SKU match
                    const now = Date.now();
                    if (now - searchInputLastKeyTime < 85) {
                        searchInputRapidStreak += 1;
                    } else {
                        searchInputRapidStreak = 1;
                    }
                    searchInputLastKeyTime = now;

                    if (searchInputAutoScanTimer) clearTimeout(searchInputAutoScanTimer);
                    const trimmed = String(searchQuery || '').trim();
                    if (trimmed.length >= 4 && searchInputRapidStreak >= 4) {
                        searchInputAutoScanTimer = setTimeout(() => {
                            const exactCodeHit = findProductByCode(trimmed);
                            if (exactCodeHit) {
                                handleScannedCode(trimmed);
                                clearSearchField();
                                searchInputRapidStreak = 0;
                            }
                        }, 180);
                    }
                });

                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === 'Tab') {
                        const code = String(searchInput.value || '').trim();
                        if (!code) {
                            if (e.key === 'Enter') e.preventDefault();
                            return;
                        }
                        if (e.key === 'Enter' || findProductByCode(code)) {
                            e.preventDefault();
                            if (searchInputAutoScanTimer) clearTimeout(searchInputAutoScanTimer);
                            handleScannedCode(code);
                            clearSearchField();
                            searchInputRapidStreak = 0;
                        }
                    }
                });
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    clearSearchField();
                    if (searchInput) searchInput.focus();
                });
            }

            // Category Filter pills
            document.querySelectorAll('.category-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.category-filter-btn').forEach(b => {
                        b.className = "category-filter-btn px-3 py-1.5 rounded-xl font-semibold transition-all whitespace-nowrap bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 shadow-xs";
                    });
                    btn.className = "category-filter-btn px-3 py-1.5 rounded-xl font-semibold transition-all whitespace-nowrap bg-indigo-600 text-white shadow-xs";

                    selectedCategory = btn.getAttribute('data-category-id');
                    renderCatalog();
                });
            });

            // Promo change
            const promoSelect = document.getElementById('cartPromoSelect');
            if (promoSelect) {
                promoSelect.addEventListener('change', evaluateCartPromo);
            }

            // Customer change
            const customerSelect = document.getElementById('cartCustomerSelect');
            if (customerSelect) {
                customerSelect.addEventListener('change', evaluateCartPromo);
            }

            // Clear Cart Button
            const clearCartBtn = document.getElementById('clearCartBtn');
            if (clearCartBtn) {
                clearCartBtn.addEventListener('click', async () => {
                    if (cart.length === 0) return;

                    const ok = typeof window.confirmAction === 'function'
                        ? await window.confirmAction({
                            title: 'Kosongkan Keranjang',
                            message: 'Kosongkan semua item dalam keranjang pesanan kasir?',
                            confirmText: 'Kosongkan',
                            variant: 'danger',
                            icon: 'trash-2'
                        })
                        : true;

                    if (ok) {
                        clearCart();
                        if (typeof showToast === 'function') {
                            showToast('info', 'Keranjang pesanan telah dikosongkan.');
                        }
                    }
                });
            }

            // Payment method buttons
            document.querySelectorAll('.payment-method-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectPaymentMethod(btn.getAttribute('data-method'));
                });
            });

            // Quick cash buttons
            document.querySelectorAll('.quick-cash-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const val = Number(btn.getAttribute('data-val'));
                    document.getElementById('cashReceivedInput').value = val;
                    calculateCashChange();
                });
            });

            const exactCashBtn = document.getElementById('exactCashBtn');
            if (exactCashBtn) {
                exactCashBtn.addEventListener('click', () => {
                    const totals = computeCartTotals();
                    document.getElementById('cashReceivedInput').value = totals.total;
                    calculateCashChange();
                });
            }

            // Cash input change
            const cashInput = document.getElementById('cashReceivedInput');
            if (cashInput) {
                cashInput.addEventListener('input', calculateCashChange);
            }

            // Keyboard Shortcuts & Global Hardware Barcode Scanner (Alat Scanner USB/Bluetooth)
            if (!window.__posShortcutsBound) {
                window.__posShortcutsBound = true;
                let globalScanBuffer = '';
                let globalScanLastTime = 0;
                let globalScanClearTimer = null;
                let lastAutoSubmittedAt = 0;

                document.addEventListener('keydown', (e) => {
                    if (!document.getElementById('productGrid')) return;
                    const activeEl = document.activeElement;
                    const activeTag = activeEl ? activeEl.tagName.toLowerCase() : '';
                    const inputType = (activeTag === 'input' && activeEl.type) ? activeEl.type.toLowerCase() : '';
                    const isTextInput = activeTag === 'input' && !['range', 'checkbox', 'radio', 'button', 'submit', 'file'].includes(inputType);
                    const isEditing = isTextInput || activeTag === 'textarea' || activeTag === 'select' || Boolean(activeEl && activeEl.isContentEditable);

                    // F2: Checkout
                    if (e.key === 'F2') {
                        e.preventDefault();
                        openPaymentModal();
                        return;
                    }

                    // Esc: Close Modal
                    if (e.key === 'Escape') {
                        closePaymentModal();
                        closeSuccessModal();
                        closeOpenShiftModal();
                        closeCloseShiftModal();
                        return;
                    }

                    // '/' Focus search
                    if (e.key === '/' && !isEditing && !globalScanBuffer) {
                        e.preventDefault();
                        const currentSearchInput = document.getElementById('catalogSearchInput');
                        if (currentSearchInput) {
                            currentSearchInput.focus();
                            currentSearchInput.select();
                        }
                        return;
                    }

                    // Global Hardware Scanner (Alat Scanner USB/Bluetooth) when not focused in a text input
                    if (!isEditing && !e.ctrlKey && !e.altKey && !e.metaKey) {
                        const now = Date.now();
                        // Keep buffer alive for up to 480ms between keystrokes so all USB/Bluetooth scanners work reliably
                        if (now - globalScanLastTime > 480) {
                            globalScanBuffer = '';
                        }

                        if (e.key === 'Enter' || e.key === 'Tab') {
                            const code = globalScanBuffer.trim();
                            globalScanBuffer = '';
                            if (globalScanClearTimer) clearTimeout(globalScanClearTimer);
                            if (code.length >= 2) {
                                e.preventDefault();
                                e.stopPropagation();
                                lastAutoSubmittedAt = now;
                                handleScannedCode(code);
                            } else if (now - lastAutoSubmittedAt < 350) {
                                // Prevent trailing Enter from clicking a focused button right after auto-scan
                                e.preventDefault();
                                e.stopPropagation();
                            }
                            return;
                        }

                        if (e.key && e.key.length === 1) {
                            globalScanBuffer += e.key;
                            globalScanLastTime = now;
                            if (globalScanClearTimer) clearTimeout(globalScanClearTimer);

                            const candidate = globalScanBuffer.trim();
                            globalScanClearTimer = setTimeout(() => {
                                // Support scanners configured without Enter/CR suffix if buffer matches an exact barcode/SKU
                                if (candidate.length >= 4 && findProductByCode(candidate)) {
                                    lastAutoSubmittedAt = Date.now();
                                    handleScannedCode(candidate);
                                    globalScanBuffer = '';
                                } else if (Date.now() - globalScanLastTime >= 480) {
                                    globalScanBuffer = '';
                                }
                            }, 260);
                        }
                    }
                }, true);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPosPage);
        } else {
            initPosPage();
        }
        document.addEventListener('livewire:navigated', initPosPage);
    </script>
    {{-- Reusable Camera Scanner Modal --}}
    <x-ui.camera-scanner-modal />
</x-layouts::pos>
