@php
    $permissions = $dashboardPermissions ?? [];
    $can = fn (string $permission): bool => in_array('*', $permissions, true)
        || in_array($permission, $permissions, true);
    $P = \App\Services\Authorization\BusinessPermission::class;

    $menuItems = [];

    // Ringkasan
    if ($can($P::DASHBOARD_VIEW)) {
        $menuItems[] = [
            'title' => 'Dashboard',
            'category' => 'Ringkasan',
            'desc' => 'Ringkasan omset penjualan, grafik metrik & status bisnis',
            'url' => route('dashboard'),
            'icon' => 'layout-dashboard',
            'keywords' => ['dashboard', 'ringkasan', 'beranda', 'home', 'grafik', 'omset', 'penjualan hari ini', 'metrik', 'statistik'],
        ];
    }

    // Operasional
    if ($can($P::POS_SELL)) {
        $menuItems[] = [
            'title' => 'Zona Kasir (Transaksi Baru POS)',
            'category' => 'Operasional',
            'desc' => 'Mode kasir layar penuh untuk melayani pesanan dan checkout',
            'url' => route('pos.index'),
            'icon' => 'shopping-cart',
            'keywords' => ['kasir', 'pos', 'transaksi baru', 'jual', 'checkout', 'zona kasir', 'order', 'pesanan baru'],
        ];
    }

    if ($can($P::PRODUCTS_VIEW)) {
        $menuItems[] = [
            'title' => 'Produk & Layanan',
            'category' => 'Operasional',
            'desc' => 'Katalog barang, jasa, resep HPP & persediaan bahan',
            'url' => route('products.index'),
            'icon' => 'package',
            'keywords' => ['produk', 'layanan', 'katalog', 'barang', 'menu', 'makanan', 'minuman', 'jasa', 'hpp', 'resep', 'bom', 'harga jual', 'modal', 'persediaan', 'bahan'],
        ];
        $menuItems[] = [
            'title' => 'Katalog Produk (Siap Jual)',
            'category' => 'Operasional',
            'desc' => 'Daftar produk jadi siap jual di kasir POS',
            'url' => route('products.index', ['tab' => 'products']),
            'icon' => 'coffee',
            'keywords' => ['produk', 'katalog', 'menu siap jual', 'pos', 'makanan', 'minuman', 'snack', 'barang dagang'],
        ];
        $menuItems[] = [
            'title' => 'Persediaan & Bahan Baku',
            'category' => 'Operasional',
            'desc' => 'Bahan baku resep HPP & persediaan stok non-jual',
            'url' => route('products.index', ['tab' => 'supplies']),
            'icon' => 'warehouse',
            'keywords' => ['persediaan', 'bahan baku', 'bahan', 'supplies', 'raw', 'biji kopi', 'susu', 'gula', 'cup', 'kemasan', 'dapur', 'resep'],
        ];
        $menuItems[] = [
            'title' => 'Layanan & Servis',
            'category' => 'Operasional',
            'desc' => 'Katalog jasa & layanan operasional tanpa stok fisik',
            'url' => route('products.index', ['tab' => 'services']),
            'icon' => 'sparkles',
            'keywords' => ['layanan', 'servis', 'jasa', 'sewa', 'ongkir', 'laundry kiloan', 'durasi', 'tarif'],
        ];
        $menuItems[] = [
            'title' => 'Kategori Produk',
            'category' => 'Operasional',
            'desc' => 'Struktur pengelompokan kategori produk & persediaan',
            'url' => route('products.index', ['tab' => 'categories']),
            'icon' => 'tags',
            'keywords' => ['kategori', 'category', 'kelompok', 'grup produk', 'kategori bahan'],
        ];
    }

    if ($can($P::STOCK_VIEW)) {
        $menuItems[] = [
            'title' => 'Stok Barang',
            'category' => 'Operasional',
            'desc' => 'Monitor sisa stok fisik, status aman/menipis/habis & nilai aset',
            'url' => route('stock.index'),
            'icon' => 'boxes',
            'keywords' => ['stok', 'stock', 'inventory', 'sisa stok', 'gudang', 'jumlah barang', 'nilai stok', 'persediaan barang'],
        ];
        $menuItems[] = [
            'title' => 'Riwayat Stok',
            'category' => 'Operasional',
            'desc' => 'Kartu stok, penerimaan barang masuk supplier & penyesuaian',
            'url' => route('stock.history'),
            'icon' => 'history',
            'keywords' => ['riwayat stok', 'barang masuk', 'penerimaan', 'beli barang', 'supplier', 'mutasi stok', 'penyesuaian', 'kartu stok', 'stok keluar'],
        ];
        $menuItems[] = [
            'title' => 'Stock Opname',
            'category' => 'Operasional',
            'desc' => 'Pengecekan fisik berkala & rekonsiliasi selisih stok',
            'url' => route('stock.opname.index'),
            'icon' => 'clipboard-check',
            'keywords' => ['stock opname', 'opname', 'hitung fisik', 'audit', 'selisih stok', 'cek fisik'],
        ];
    }

    if ($can($P::PROMO_VIEW)) {
        $menuItems[] = [
            'title' => 'Promo & Diskon',
            'category' => 'Operasional',
            'desc' => 'Program promo potongan harga, diskon produk & kupon',
            'url' => route('promos.index'),
            'icon' => 'badge-percent',
            'keywords' => ['promo', 'diskon', 'potongan', 'voucher', 'kupon', 'cashback', 'sale', 'potongan harga'],
        ];
    }

    if ($can($P::TRANSACTIONS_VIEW)) {
        $menuItems[] = [
            'title' => 'Transaksi Penjualan',
            'category' => 'Operasional',
            'desc' => 'Daftar riwayat transaksi kasir dan struk penjualan',
            'url' => route('transactions.index'),
            'icon' => 'receipt',
            'keywords' => ['transaksi', 'penjualan', 'struk', 'nota', 'kasir', 'order', 'pesanan', 'invoice'],
        ];
    }

    if ($can($P::CASH_VIEW)) {
        $menuItems[] = [
            'title' => 'Kas & Pengeluaran',
            'category' => 'Operasional',
            'desc' => 'Catatan kas masuk, biaya operasional & petty cash toko',
            'url' => route('cash.index'),
            'icon' => 'wallet-cards',
            'keywords' => ['kas', 'pengeluaran', 'biaya', 'uang keluar', 'uang masuk', 'beban', 'petty cash', 'operasional'],
        ];
    }

    if ($can($P::SHIFTS_VIEW)) {
        $menuItems[] = [
            'title' => 'Shift Kasir',
            'category' => 'Operasional',
            'desc' => 'Jam kerja kasir, modal awal kasir & rekap kas akhir',
            'url' => route('shifts.index'),
            'icon' => 'clock',
            'keywords' => ['shift', 'kasir', 'buka shift', 'tutup shift', 'modal kasir', 'laci kas', 'jam kerja'],
        ];
    }

    if ($can($P::CUSTOMERS_VIEW)) {
        $menuItems[] = [
            'title' => 'Pelanggan',
            'category' => 'Operasional',
            'desc' => 'Database pelanggan, kontak member & riwayat belanja',
            'url' => route('customers.index'),
            'icon' => 'users',
            'keywords' => ['pelanggan', 'customer', 'member', 'kontak', 'telepon', 'data pembeli'],
        ];
    }

    // Analisis
    if ($can($P::REPORTS_VIEW)) {
        $menuItems[] = [
            'title' => 'Laporan Bisnis',
            'category' => 'Analisis',
            'desc' => 'Laporan laba kotor, ringkasan omset & ekspor PDF',
            'url' => route('reports.index'),
            'icon' => 'chart-column',
            'keywords' => ['laporan', 'report', 'laba', 'profit', 'laba rugi', 'omset', 'analisis', 'pdf', 'keuangan'],
        ];
    }

    // Bisnis
    if ($can($P::BUSINESS_SETTINGS_MANAGE)) {
        $menuItems[] = [
            'title' => 'Pengaturan Bisnis',
            'category' => 'Bisnis',
            'desc' => 'Profil usaha, identitas toko & penentuan tipe bisnis',
            'url' => route('business-settings.edit'),
            'icon' => 'briefcase-business',
            'keywords' => ['pengaturan bisnis', 'profil bisnis', 'tipe bisnis', 'nama toko', 'cafe', 'laundry', 'grosir', 'identitas usaha'],
        ];
    }

    if ($can($P::USERS_VIEW)) {
        $menuItems[] = [
            'title' => 'Pengguna & Kasir',
            'category' => 'Bisnis',
            'desc' => 'Kelola akun staf, kasir dan pembagian hak akses (role)',
            'url' => route('users.index'),
            'icon' => 'user-cog',
            'keywords' => ['pengguna', 'user', 'staf', 'kasir', 'karyawan', 'role', 'hak akses', 'tambah kasir', 'admin'],
        ];
    }

    if ($can($P::DEVICES_VIEW)) {
        $menuItems[] = [
            'title' => 'Perangkat POS',
            'category' => 'Bisnis',
            'desc' => 'Otorisasi tablet/HP kasir offline & manajemen perangkat',
            'url' => route('devices.index'),
            'icon' => 'monitor-smartphone',
            'keywords' => ['perangkat', 'device', 'mesin kasir', 'tablet', 'hp pos', 'aplikasi pos', 'otorisasi device'],
        ];
    }

    if ($can($P::OUTLETS_VIEW)) {
        $menuItems[] = [
            'title' => 'Outlet / Cabang',
            'category' => 'Bisnis',
            'desc' => 'Daftar cabang toko dan informasi outlet bisnis',
            'url' => route('outlets.index'),
            'icon' => 'store',
            'keywords' => ['outlet', 'cabang', 'toko', 'lokasi', 'cabang bisnis'],
        ];
    }

    // Cloud & Sistem
    if ($can($P::SYNC_VIEW)) {
        $menuItems[] = [
            'title' => 'Sinkronisasi Cloud',
            'category' => 'Cloud & Sistem',
            'desc' => 'Status sync database offline ke cloud dan backup',
            'url' => route('sync.index'),
            'icon' => 'refresh-cw',
            'keywords' => ['sinkronisasi', 'sync', 'cloud', 'backup', 'status koneksi', 'offline online'],
        ];
    }

    if ($can($P::SUBSCRIPTION_MANAGE)) {
        $menuItems[] = [
            'title' => 'Paket Langganan',
            'category' => 'Cloud & Sistem',
            'desc' => 'Status langganan cloud NexaPOS, upgrade & billing',
            'url' => route('subscriptions.index'),
            'icon' => 'credit-card',
            'keywords' => ['langganan', 'paket', 'subscription', 'billing', 'bayar', 'upgrade', 'premium', 'cloud'],
        ];
    }

    // Pengaturan Akun (selalu ada untuk user terautentikasi)
    $menuItems[] = [
        'title' => 'Pengaturan Akun & Profil',
        'category' => 'Pengaturan',
        'desc' => 'Edit profil pemilik, email, kata sandi & keamanan akun',
        'url' => route('profile.edit'),
        'icon' => 'settings',
        'keywords' => ['pengaturan', 'profil', 'akun', 'password', 'ganti password', 'keamanan', 'email', 'settings'],
    ];

    $encodedMenuItems = base64_encode(json_encode($menuItems, JSON_UNESCAPED_UNICODE));
@endphp

{{-- Safe Base64-encoded Data Script for Client-side Search --}}
<script type="application/json" id="globalMenuSearchData">{{ $encodedMenuItems }}</script>

{{-- ==================== GLOBAL MENU SEARCH / QUICKNAV ==================== --}}
<div id="globalMenuSearchRoot" class="relative w-full">

    {{-- Desktop Search Input --}}
    <div class="relative w-full">
        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
        <input
            type="text"
            id="globalMenuSearchInput"
            autocomplete="off"
            placeholder="Cari menu atau fitur..."
            class="w-full pl-9 pr-14 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-800/40 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 hover:bg-slate-100/70 dark:hover:bg-slate-800/70 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
            aria-label="Cari menu atau fitur dashboard"
        />
        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <button
                type="button"
                id="globalMenuSearchClearBtn"
                class="hidden p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                aria-label="Bersihkan pencarian"
            >
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
            <kbd class="px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-[10px] font-mono text-slate-400 bg-white dark:bg-slate-800 pointer-events-none select-none">
                /
            </kbd>
        </div>
    </div>

    {{-- Desktop Dropdown Panel --}}
    <div
        id="globalMenuSearchPanel"
        class="hidden absolute top-full left-0 mt-2 w-full min-w-[340px] sm:min-w-[440px] max-w-lg bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-2xl z-50 overflow-hidden text-xs"
    >
        <div class="px-3.5 py-2 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-500 bg-slate-50/60 dark:bg-slate-950/40">
            <span class="font-semibold">Navigasi Fitur Menu</span>
            <span id="globalMenuSearchResultCount" class="font-medium">menu ditemukan</span>
        </div>

        <div id="globalMenuSearchResultsList" class="max-h-[380px] overflow-y-auto p-1.5 space-y-1">
            {{-- Dynamically populated via JS --}}
        </div>

        <div class="px-3 py-2 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/40 flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 select-none">
            <div class="flex items-center gap-2.5">
                <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono">↑↓</kbd> Pilih</span>
                <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono">↵</kbd> Buka</span>
                <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono">ESC</kbd> Tutup</span>
            </div>
            <span>NexaPOS QuickNav</span>
        </div>
    </div>

</div>

{{-- Mobile Search Modal Backdrop & Dialog --}}
<div id="mobileMenuSearchModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden md:hidden flex flex-col p-4">
    <div class="w-full max-w-md mx-auto bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[85vh]">
        <div class="p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 shrink-0"></i>
            <input
                type="text"
                id="mobileMenuSearchInput"
                autocomplete="off"
                placeholder="Cari menu atau fitur..."
                class="flex-1 text-sm bg-transparent border-none text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none"
            />
            <button
                type="button"
                id="mobileMenuSearchCloseBtn"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                aria-label="Tutup pencarian"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div id="mobileMenuSearchResultsList" class="flex-1 overflow-y-auto p-2 space-y-1 text-xs">
            {{-- Dynamically populated via JS --}}
        </div>
    </div>
</div>

<script>
(function() {
    function getMenuItems() {
        const dataEl = document.getElementById('globalMenuSearchData');
        if (!dataEl || !dataEl.textContent) return [];
        try {
            return JSON.parse(atob(dataEl.textContent.trim()));
        } catch (e) {
            return [];
        }
    }

    let activeIndex = -1;
    let filteredItems = [];

    const desktopInput = document.getElementById('globalMenuSearchInput');
    const desktopPanel = document.getElementById('globalMenuSearchPanel');
    const desktopResultsList = document.getElementById('globalMenuSearchResultsList');
    const desktopCountEl = document.getElementById('globalMenuSearchResultCount');
    const desktopClearBtn = document.getElementById('globalMenuSearchClearBtn');

    const mobileModal = document.getElementById('mobileMenuSearchModal');
    const mobileInput = document.getElementById('mobileMenuSearchInput');
    const mobileResultsList = document.getElementById('mobileMenuSearchResultsList');
    const mobileCloseBtn = document.getElementById('mobileMenuSearchCloseBtn');

    function filterMenus(query) {
        const items = getMenuItems();
        const cleanQ = query.trim().toLowerCase();
        if (!cleanQ) return items;

        return items.filter(item => {
            if (item.title.toLowerCase().includes(cleanQ)) return true;
            if (item.desc.toLowerCase().includes(cleanQ)) return true;
            if (item.category.toLowerCase().includes(cleanQ)) return true;
            return item.keywords.some(k => k.toLowerCase().includes(cleanQ));
        });
    }

    function renderItems(items, container, isMobile = false) {
        if (!container) return;
        container.innerHTML = '';
        activeIndex = items.length > 0 ? 0 : -1;

        if (items.length === 0) {
            container.innerHTML = `
                <div class="py-8 px-4 text-center text-slate-400 dark:text-slate-500">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-2 text-slate-400">
                        <i data-lucide="search-x" class="w-4 h-4"></i>
                    </div>
                    <p class="font-semibold text-xs text-slate-700 dark:text-slate-300">Tidak ada fitur yang cocok</p>
                    <p class="text-[11px] mt-0.5">Coba gunakan kata kunci lain seperti <em>hpp</em>, <em>stok</em>, <em>promo</em>, atau <em>laporan</em>.</p>
                </div>
            `;
            if (typeof window.initIcons === 'function') window.initIcons();
            return;
        }

        items.forEach((item, idx) => {
            const a = document.createElement('a');
            a.href = item.url;
            a.className = `menu-search-item flex items-center justify-between gap-3 p-2.5 rounded-xl text-slate-700 dark:text-slate-200 transition-colors group cursor-pointer ${idx === 0 ? 'bg-indigo-50/80 dark:bg-indigo-950/60 ring-1 ring-indigo-500/30' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'}`;
            a.setAttribute('data-index', idx);

            a.innerHTML = `
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 text-slate-500 group-hover:text-indigo-600 dark:text-slate-400 dark:group-hover:text-indigo-400 flex items-center justify-center shrink-0 transition-colors">
                        <i data-lucide="${item.icon}" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 truncate">
                            ${item.title}
                        </div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
                            ${item.desc}
                        </div>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 shrink-0">
                    ${item.category}
                </span>
            `;

            container.appendChild(a);
        });

        if (typeof window.initIcons === 'function') {
            window.initIcons();
        }
    }

    function updateActiveHighlight(container) {
        if (!container) return;
        const allItems = container.querySelectorAll('.menu-search-item');
        allItems.forEach((el, idx) => {
            if (idx === activeIndex) {
                el.classList.add('bg-indigo-50/80', 'dark:bg-indigo-950/60', 'ring-1', 'ring-indigo-500/30');
                el.classList.remove('hover:bg-slate-50', 'dark:hover:bg-slate-800/60');
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                el.classList.remove('bg-indigo-50/80', 'dark:bg-indigo-950/60', 'ring-1', 'ring-indigo-500/30');
                el.classList.add('hover:bg-slate-50', 'dark:hover:bg-slate-800/60');
            }
        });
    }

    function openDesktopPanel() {
        if (!desktopPanel) return;
        desktopPanel.classList.remove('hidden', 'dropdown-hidden');
        filteredItems = filterMenus(desktopInput ? desktopInput.value : '');
        if (desktopCountEl) {
            desktopCountEl.textContent = `${filteredItems.length} menu ditemukan`;
        }
        renderItems(filteredItems, desktopResultsList, false);
    }

    function closeDesktopPanel() {
        if (!desktopPanel) return;
        desktopPanel.classList.add('hidden');
        desktopPanel.classList.remove('dropdown-hidden');
        activeIndex = -1;
    }

    // Desktop Input Listeners
    if (desktopInput) {
        desktopInput.addEventListener('focus', () => {
            openDesktopPanel();
        });

        desktopInput.addEventListener('click', (e) => {
            e.stopPropagation();
            openDesktopPanel();
        });

        desktopInput.addEventListener('input', (e) => {
            const val = e.target.value;
            if (desktopClearBtn) {
                desktopClearBtn.classList.toggle('hidden', !val);
            }
            openDesktopPanel();
        });

        desktopInput.addEventListener('keydown', (e) => {
            if (!desktopPanel || desktopPanel.classList.contains('hidden')) {
                if (e.key === 'ArrowDown' || e.key === 'Enter') {
                    openDesktopPanel();
                    e.preventDefault();
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (filteredItems.length > 0) {
                    activeIndex = (activeIndex + 1) % filteredItems.length;
                    updateActiveHighlight(desktopResultsList);
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (filteredItems.length > 0) {
                    activeIndex = (activeIndex - 1 + filteredItems.length) % filteredItems.length;
                    updateActiveHighlight(desktopResultsList);
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (filteredItems.length > 0 && activeIndex >= 0 && activeIndex < filteredItems.length) {
                    window.location.href = filteredItems[activeIndex].url;
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeDesktopPanel();
                desktopInput.blur();
            }
        });
    }

    if (desktopPanel) {
        desktopPanel.addEventListener('click', (e) => {
            // Prevent clicks inside panel from bubbling to document click handlers
            e.stopPropagation();
        });
    }

    if (desktopClearBtn) {
        desktopClearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (desktopInput) {
                desktopInput.value = '';
                desktopInput.focus();
                desktopClearBtn.classList.add('hidden');
                openDesktopPanel();
            }
        });
    }

    // Close when clicking outside desktop
    document.addEventListener('click', (e) => {
        const root = document.getElementById('globalMenuSearchRoot');
        if (root && !root.contains(e.target)) {
            closeDesktopPanel();
        }
    });

    // Global keyboard shortcut '/' or 'Ctrl+K' / 'Cmd+K'
    document.addEventListener('keydown', (e) => {
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const isEditing = activeTag === 'input' || activeTag === 'textarea' || (document.activeElement && document.activeElement.isContentEditable);

        if (e.key === '/' && !isEditing) {
            e.preventDefault();
            if (window.innerWidth >= 768 && desktopInput) {
                desktopInput.focus();
                desktopInput.select();
                openDesktopPanel();
            } else {
                openMobileSearch();
            }
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (window.innerWidth >= 768 && desktopInput) {
                desktopInput.focus();
                desktopInput.select();
                openDesktopPanel();
            } else {
                openMobileSearch();
            }
        }
    });

    // Mobile Search Modal Logic
    function openMobileSearch() {
        if (!mobileModal) return;
        mobileModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        filteredItems = filterMenus('');
        renderItems(filteredItems, mobileResultsList, true);
        setTimeout(() => {
            if (mobileInput) {
                mobileInput.focus();
            }
        }, 100);
    }
    window.openMobileSearch = openMobileSearch;

    function closeMobileSearch() {
        if (!mobileModal) return;
        mobileModal.classList.add('hidden');
        document.body.style.overflow = '';
        if (mobileInput) mobileInput.value = '';
    }

    if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', closeMobileSearch);
    }

    if (mobileModal) {
        mobileModal.addEventListener('click', (e) => {
            if (e.target === mobileModal) {
                closeMobileSearch();
            }
        });
    }

    if (mobileInput) {
        mobileInput.addEventListener('input', (e) => {
            filteredItems = filterMenus(e.target.value);
            renderItems(filteredItems, mobileResultsList, true);
        });

        mobileInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (filteredItems.length > 0) {
                    window.location.href = filteredItems[0].url;
                }
            } else if (e.key === 'Escape') {
                closeMobileSearch();
            }
        });
    }
})();
</script>
