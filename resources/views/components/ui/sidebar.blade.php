@props([
    'businessContext' => 'cafe', // 'cafe', 'laundry', 'grosir'
    'subscription' => 'unknown', // 'subscriber', 'free', 'unknown'
])

@php
    $subConfig = match($subscription) {
        'subscriber' => [
            'badgeClass' => 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400',
            'dotClass' => 'bg-emerald-500',
            'badgeText' => 'Aktif',
            'description' => 'Akses sinkronisasi Cloud aktif',
            'buttonText' => 'Kelola Paket',
            'miniDotClass' => 'bg-emerald-500 ring-1 ring-white dark:ring-slate-900',
            'miniTooltip' => 'Paket Cloud: Subscriber Aktif',
            'miniAria' => 'Status Paket Cloud: Subscriber Aktif',
        ],
        'free' => [
            'badgeClass' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
            'dotClass' => 'bg-slate-400',
            'badgeText' => 'Gratis',
            'description' => 'Mode lokal tanpa sinkronisasi Cloud',
            'buttonText' => 'Tingkatkan Paket',
            'miniDotClass' => 'bg-slate-400 ring-1 ring-white dark:ring-slate-900',
            'miniTooltip' => 'Paket Cloud: Versi Gratis',
            'miniAria' => 'Status Paket Cloud: Versi Gratis',
        ],
        default => [ // 'unknown'
            'badgeClass' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
            'dotClass' => 'bg-slate-400 dark:bg-slate-500',
            'badgeText' => 'Standar',
            'description' => 'Status belum terhubung',
            'buttonText' => 'Kelola Paket',
            'miniDotClass' => 'bg-slate-400 ring-1 ring-white dark:ring-slate-900',
            'miniTooltip' => 'Paket Cloud: Belum Terhubung',
            'miniAria' => 'Status Paket Cloud: Belum Terhubung',
        ],
    };
@endphp

{{-- ==================== SIDEBAR COMPONENT ==================== --}}
<aside
    id="sidebar"
    class="fixed top-0 left-0 z-50 h-full w-72 bg-white dark:bg-slate-900 border-r border-slate-200/90 dark:border-slate-800 sidebar-transition flex flex-col -translate-x-full md:translate-x-0 select-none"
    aria-label="Navigasi Utama"
    data-business-context="{{ $businessContext }}"
>
    {{-- Sidebar Header / Brand Logo --}}
    <div class="flex items-center justify-between h-16 px-4 sm:px-5 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-xl" aria-label="NexaPOS Dashboard">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-sm shadow-indigo-600/20 shrink-0">
                <i data-lucide="zap" class="w-5 h-5"></i>
            </div>
            <span class="sidebar-logo-text font-extrabold text-lg tracking-tight text-slate-900 dark:text-white">
                Nexa<span class="text-indigo-600 dark:text-indigo-400">POS</span>
            </span>
        </a>

        {{-- Mobile Drawer Close Button (X) --}}
        <button
            type="button"
            id="closeSidebarBtn"
            class="md:hidden p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            aria-label="Tutup navigasi"
        >
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Active Business Switcher / Context --}}
    <x-dashboard.business-switcher />

    {{-- Navigation Menu Container --}}
    <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-4" aria-label="Menu Utama">

        {{-- 1. RINGKASAN --}}
        <x-ui.sidebar-section title="Ringkasan">
            <x-ui.sidebar-item
                icon="layout-dashboard"
                label="Dashboard"
                route="dashboard"
            />
        </x-ui.sidebar-section>

        {{-- 2. OPERASIONAL --}}
        <x-ui.sidebar-section title="Operasional">
            @if($businessContext === 'laundry')
                <x-ui.sidebar-item
                    icon="washing-machine"
                    label="Pesanan Laundry"
                />
            @endif
            <x-ui.sidebar-item
                icon="receipt"
                label="Transaksi"
                route="transactions.index"
            />
            <x-ui.sidebar-item
                icon="package"
                label="Produk & Layanan"
                route="products.index"
            />
            <x-ui.sidebar-item
                icon="boxes"
                label="Stok"
                route="stock.index"
            />
            <x-ui.sidebar-item
                icon="wallet-cards"
                label="Kas & Pengeluaran"
                route="cash.index"
            />
            <x-ui.sidebar-item
                icon="clock"
                label="Shift"
                route="shifts.index"
            />
            <x-ui.sidebar-item
                icon="users"
                label="Pelanggan"
            />
        </x-ui.sidebar-section>

        {{-- 3. ANALISIS --}}
        <x-ui.sidebar-section title="Analisis">
            <x-ui.sidebar-item
                icon="chart-column"
                label="Laporan"
                route="reports.index"
            />
        </x-ui.sidebar-section>

        {{-- 4. BISNIS --}}
        <x-ui.sidebar-section title="Bisnis">
            <x-ui.sidebar-item
                icon="store"
                label="Outlet"
                route="outlets.index"
            />
            <x-ui.sidebar-item
                icon="user-cog"
                label="Pengguna / Kasir"
            />
            <x-ui.sidebar-item
                icon="monitor-smartphone"
                label="Perangkat"
                route="devices.index"
            />
        </x-ui.sidebar-section>

        {{-- 5. CLOUD & SISTEM --}}
        <x-ui.sidebar-section title="Cloud & Sistem">
            <x-ui.sidebar-item
                icon="refresh-cw"
                label="Sinkronisasi"
                route="sync.index"
            />
            <x-ui.sidebar-item
                icon="credit-card"
                label="Langganan"
            />
            <x-ui.sidebar-item
                icon="settings"
                label="Pengaturan"
                route="profile.edit"
            />
        </x-ui.sidebar-section>

    </nav>

    {{-- Compact Redesigned Cloud Plan Card --}}
    <div class="px-3 pb-3 shrink-0">
        {{-- Expanded State Card --}}
        <div class="sidebar-cloud-card rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 p-3">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="cloud" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Paket Cloud</span>
                </div>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-bold {{ $subConfig['badgeClass'] }}">
                    <span class="w-1 h-1 rounded-full {{ $subConfig['dotClass'] }}"></span>
                    {{ $subConfig['badgeText'] }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">
                {{ $subConfig['description'] }}
            </p>
            <button
                type="button"
                id="managePlanBtn"
                class="mt-2 w-full py-1.5 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                {{ $subConfig['buttonText'] }}
            </button>
        </div>

        {{-- Collapsed State Mini Icon --}}
        <div class="sidebar-cloud-mini hidden justify-center py-2">
            <button
                type="button"
                id="managePlanMiniBtn"
                class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-colors relative focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-tooltip-right="{{ $subConfig['miniTooltip'] }}"
                aria-label="{{ $subConfig['miniAria'] }}"
            >
                <i data-lucide="cloud" class="w-4 h-4"></i>
                <span class="absolute top-2 right-2 w-1.5 h-1.5 rounded-full {{ $subConfig['miniDotClass'] }}"></span>
            </button>
        </div>
    </div>

    {{-- Collapse Desktop Toggle Button at bottom --}}
    <button
        type="button"
        id="collapseSidebarBtn"
        class="hidden md:flex items-center justify-center gap-2 py-3 px-3 border-t border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-800 dark:hover:text-slate-200 transition-colors shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        aria-label="Ciutkan navigasi sidebar"
        data-tooltip-right="Ciutkan Sidebar"
    >
        <i data-lucide="chevrons-left" class="w-4 h-4 transition-transform duration-200" id="collapseIcon"></i>
        <span class="sidebar-label text-xs font-medium" id="collapseLabel">Ciutkan</span>
    </button>
</aside>
