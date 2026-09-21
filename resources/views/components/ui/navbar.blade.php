@props([
    'title' => null,
    'breadcrumbItems' => [],
    'cloudStatus' => 'unknown', // 'online', 'offline', 'unknown'
])

@php
    $cloudConfig = match($cloudStatus) {
        'online' => [
            'pill' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/70 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400',
            'dot' => 'bg-emerald-500 animate-pulse',
            'label' => 'Online',
            'tooltip' => 'Koneksi Cloud: Terhubung',
        ],
        'offline' => [
            'pill' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200/70 dark:border-amber-800/50 text-amber-700 dark:text-amber-400',
            'dot' => 'bg-amber-500',
            'label' => 'Offline',
            'tooltip' => 'Koneksi Cloud: Terputus',
        ],
        default => [ // 'unknown'
            'pill' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
            'dot' => 'bg-slate-400 dark:bg-slate-500',
            'label' => 'Status Cloud',
            'tooltip' => 'Status koneksi cloud akan tersedia setelah integrasi data',
        ],
    };
@endphp

{{-- ==================== NAVBAR / TOPBAR ==================== --}}
<header id="navbar" class="sticky top-0 z-30 h-16 bg-white/85 dark:bg-slate-900/85 backdrop-blur-xl border-b border-slate-200/90 dark:border-slate-800 flex items-center justify-between px-4 md:px-6 transition-colors duration-200">

    {{-- Left: Hamburger + Desktop Collapse Toggle + Breadcrumb --}}
    <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
        <button
            type="button"
            id="hamburgerBtn"
            class="md:hidden p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            aria-label="Buka navigasi sidebar"
            aria-expanded="false"
        >
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <button
            type="button"
            id="desktopCollapseBtn"
            class="hidden md:flex p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            aria-label="Ciutkan atau bentangkan sidebar"
            data-tooltip="Ciutkan Sidebar"
            aria-expanded="true"
        >
            <i data-lucide="panel-left" class="w-5 h-5"></i>
        </button>

        <div class="min-w-0">
            <x-ui.breadcrumb :items="$breadcrumbItems" :title="$title" />
        </div>
    </div>

    {{-- Center: Global Search Placeholder (Non-destructive Visual Search UI) --}}
    <div class="hidden xl:flex items-center flex-1 max-w-xs mx-6">
        <div class="relative w-full">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            <input
                type="text"
                readonly
                onclick="showToast('info', 'Pencarian global terpusat akan tersedia setelah integrasi data.')"
                placeholder="Cari transaksi, produk, pelanggan..."
                class="w-full pl-9 pr-8 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-800/40 text-xs text-slate-700 dark:text-slate-200 placeholder:text-slate-400 dark:placeholder:text-slate-500 cursor-pointer hover:bg-slate-100/70 dark:hover:bg-slate-800/70 focus:outline-none transition-colors"
                aria-label="Pencarian cepat"
            />
            <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-[10px] font-mono text-slate-400 bg-white dark:bg-slate-800">
                /
            </kbd>
        </div>
    </div>

    {{-- Right: Status & Actions --}}
    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">

        {{-- Cloud Connection Status Pill --}}
        <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-semibold {{ $cloudConfig['pill'] }}"
            data-tooltip="{{ $cloudConfig['tooltip'] }}"
            aria-label="{{ $cloudConfig['tooltip'] }}"
        >
            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $cloudConfig['dot'] }}"></span>
            <span class="hidden sm:inline">{{ $cloudConfig['label'] }}</span>
        </span>

        {{-- Notification Dropdown --}}
        <div class="relative">
            <button
                type="button"
                id="notificationBtn"
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors relative focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Notifikasi"
                aria-haspopup="true"
                aria-expanded="false"
                data-tooltip="Notifikasi"
            >
                <i data-lucide="bell" class="w-4 h-4 sm:w-5 sm:h-5"></i>
            </button>

            {{-- Notifications Panel --}}
            <div
                id="notificationDropdown"
                class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-80 sm:w-88 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 rounded-2xl shadow-xl shadow-slate-200/40 dark:shadow-slate-950/60 overflow-hidden z-50"
                role="region"
                aria-label="Daftar Notifikasi"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
                    <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">Notifikasi</h3>
                    <button
                        type="button"
                        disabled
                        class="text-[11px] text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed"
                        title="Belum ada notifikasi yang dapat ditandai"
                    >
                        Tandai dibaca
                    </button>
                </div>

                {{-- Neutral Empty State --}}
                <div class="py-8 px-4 text-center">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800/70 text-slate-400 dark:text-slate-500 flex items-center justify-center mx-auto mb-2.5">
                        <i data-lucide="bell-off" class="w-5 h-5"></i>
                    </div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Belum Ada Notifikasi</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 max-w-[230px] mx-auto leading-normal">
                        Notifikasi operasional akan muncul secara otomatis setelah integrasi data aktif.
                    </p>
                </div>

                <div class="p-2.5 border-t border-slate-100 dark:border-slate-700/80 text-center bg-slate-50/50 dark:bg-slate-800/50">
                    <button
                        type="button"
                        onclick="showToast('info', 'Pusat notifikasi akan tersedia setelah integrasi data.')"
                        class="text-xs text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 font-semibold transition-colors"
                    >
                        Lihat Semua Notifikasi
                    </button>
                </div>
            </div>
        </div>

        {{-- Theme Toggle --}}
        <button
            type="button"
            id="themeToggleBtn"
            class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            aria-label="Ganti mode tampilan tema"
            data-tooltip="Mode Tampilan"
        >
            <i data-lucide="moon" class="w-4 h-4 sm:w-5 sm:h-5 hidden dark:inline" id="moonIcon"></i>
            <i data-lucide="sun" class="w-4 h-4 sm:w-5 sm:h-5 inline dark:hidden" id="sunIcon"></i>
        </button>

        {{-- User Profile Dropdown Component --}}
        <x-ui.user-menu />

    </div>
</header>
