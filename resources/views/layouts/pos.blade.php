<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head', ['title' => 'Zona Kasir (POS) - ' . ($dashboardCurrentBusiness->name ?? 'NexaPOS')])
        <style>
            @media print {
                body * {
                    visibility: hidden;
                }
                #thermalReceiptArea, #thermalReceiptArea * {
                    visibility: visible;
                }
                #thermalReceiptArea {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    padding: 0;
                    margin: 0;
                }
            }
        </style>
    </head>
    <body class="bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col font-sans transition-colors duration-200 overflow-x-hidden">

        {{-- ==================== POS HEADER ==================== --}}
        <header class="h-14 md:h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-3 md:px-6 flex items-center justify-between shrink-0 z-30 select-none">
            {{-- Left: Logo & Business / Outlet --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group" title="Kembali ke Dashboard">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                        <i data-lucide="zap" class="w-4 h-4"></i>
                    </div>
                    <div class="hidden sm:block">
                        <span class="font-black text-sm tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                            Nexa<span class="text-indigo-600">POS</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">KASIR</span>
                        </span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium truncate max-w-[150px] md:max-w-[220px]">
                            {{ $business->name ?? 'Bisnis' }}
                        </p>
                    </div>
                </a>

                {{-- Outlet Switcher --}}
                @if(isset($outlets) && $outlets->count() > 1)
                    <div class="relative ml-2">
                        <form id="outletSwitchForm" method="POST" action="{{ route('pos.switch-outlet') }}">
                            @csrf
                            <select
                                name="outlet_id"
                                onchange="document.getElementById('outletSwitchForm').submit()"
                                class="text-xs font-semibold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 pr-7 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                            >
                                @foreach($outlets as $out)
                                    <option value="{{ $out->id }}" {{ isset($activeOutlet) && $activeOutlet->id === $out->id ? 'selected' : '' }}>
                                        📍 {{ $out->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @else
                    <div class="hidden md:flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 px-2.5 py-1 rounded-xl border border-slate-200 dark:border-slate-800">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-500"></i>
                        <span class="font-medium">{{ $activeOutlet->name ?? 'Outlet Utama' }}</span>
                    </div>
                @endif
            </div>

            {{-- Center: Shift Badge & Control --}}
            <div class="flex items-center gap-2">
                @if(isset($currentShift) && $currentShift)
                    <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400 px-3 py-1 rounded-xl text-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="font-bold">Shift: {{ $currentShift->shift_number }}</span>
                        <span class="hidden md:inline text-emerald-600/80 dark:text-emerald-500/80 text-[11px] font-mono">
                            (Modal: Rp {{ number_format($currentShift->opening_cash, 0, ',', '.') }})
                        </span>
                        <button
                            type="button"
                            onclick="openCloseShiftModal({{ $currentShift->id }})"
                            class="ml-1 text-[11px] font-semibold underline hover:text-emerald-900 dark:hover:text-emerald-200 transition-colors"
                        >
                            Tutup Shift
                        </button>
                    </div>
                @else
                    <div class="flex items-center gap-2 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 text-amber-700 dark:text-amber-400 px-3 py-1 rounded-xl text-xs">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="font-bold">Shift Belum Dibuka</span>
                        <button
                            type="button"
                            onclick="openOpenShiftModal()"
                            class="ml-1 px-2 py-0.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] shadow-xs transition-colors"
                        >
                            Buka Shift Sekarang
                        </button>
                    </div>
                @endif
            </div>

            {{-- Right: Clock, Theme, Return to Dashboard --}}
            <div class="flex items-center gap-2">
                {{-- Live Clock --}}
                <div id="liveClock" class="hidden lg:flex items-center gap-1.5 text-xs font-mono text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>--:--:--</span>
                </div>

                {{-- Theme Switcher --}}
                <button
                    type="button"
                    id="themeToggleBtn"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                    aria-label="Ganti Tema"
                >
                    <i data-lucide="moon" id="moonIcon" class="w-4 h-4 hidden"></i>
                    <i data-lucide="sun" id="sunIcon" class="w-4 h-4 hidden"></i>
                </button>

                {{-- Dashboard Link --}}
                <a
                    href="{{ route('dashboard') }}"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-1.5 shadow-xs"
                >
                    <i data-lucide="layout-dashboard" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span class="hidden sm:inline">Dashboard</span>
                </a>
            </div>
        </header>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <main class="flex-1 flex flex-col min-h-0 overflow-hidden">
            {{ $slot }}
        </main>

        {{-- ==================== TOAST CONTAINER ==================== --}}
        <x-ui.toast-container />

        {{-- ==================== GLOBAL CONFIRMATION MODAL ==================== --}}
        <x-ui.confirmation-modal />

        {{-- ==================== PWA STATUS & INSTALL BANNER ==================== --}}
        <x-ui.pwa-banner />

        {{-- ==================== SCRIPTS ==================== --}}
        <script>
            (function() {
                // Initialize Lucide Icons
                function initIcons() {
                    if (typeof lucide !== 'undefined') {
                        try {
                            lucide.createIcons();
                        } catch (e) {}
                    }
                }
                window.initIcons = initIcons;

                // Theme Management
                function applyTheme(theme) {
                    const html = document.documentElement;
                    const moonIcon = document.getElementById('moonIcon');
                    const sunIcon = document.getElementById('sunIcon');
                    if (theme === 'dark') {
                        html.classList.add('dark');
                        html.classList.remove('light');
                        if (moonIcon) moonIcon.style.display = 'inline';
                        if (sunIcon) sunIcon.style.display = 'none';
                    } else {
                        html.classList.remove('dark');
                        html.classList.add('light');
                        if (moonIcon) moonIcon.style.display = 'none';
                        if (sunIcon) sunIcon.style.display = 'inline';
                    }
                    try {
                        localStorage.setItem('nexa-theme', theme);
                    } catch (e) {}
                }
                window.applyTheme = applyTheme;

                const storedTheme = localStorage.getItem('nexa-theme') || 'light';
                applyTheme(storedTheme);

                const themeToggleBtn = document.getElementById('themeToggleBtn');
                if (themeToggleBtn) {
                    themeToggleBtn.addEventListener('click', () => {
                        const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                        applyTheme(current === 'dark' ? 'light' : 'dark');
                    });
                }

                // Live Clock
                function updateClock() {
                    const clockEl = document.querySelector('#liveClock span');
                    if (!clockEl) return;
                    const now = new Date();
                    clockEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                setInterval(updateClock, 1000);
                updateClock();

                // Toast System
                function showToast(type, message) {
                    const container = document.getElementById('toastContainer');
                    if (!container) return;
                    const toast = document.createElement('div');
                    const icons = { success: 'check-circle', error: 'x-circle', warning: 'alert-triangle', info: 'info' };
                    const colors = {
                        success: 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
                        error: 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200',
                        warning: 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-200',
                        info: 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200'
                    };
                    const iconColors = {
                        success: 'text-green-600 dark:text-green-400',
                        error: 'text-red-600 dark:text-red-400',
                        warning: 'text-amber-600 dark:text-amber-400',
                        info: 'text-blue-600 dark:text-blue-400'
                    };
                    const iconName = icons[type] || 'info';
                    toast.className = `pointer-events-auto flex items-start gap-3 p-3.5 rounded-xl border shadow-lg shadow-slate-200/50 dark:shadow-slate-900/50 ${colors[type]} transition-all duration-300`;
                    toast.innerHTML = `
                        <i data-lucide="${iconName}" class="w-4 h-4 mt-0.5 shrink-0 ${iconColors[type]}"></i>
                        <p class="text-xs font-semibold flex-1 leading-snug">${message}</p>
                        <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors shrink-0" aria-label="Dismiss toast">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    `;
                    container.appendChild(toast);
                    initIcons();
                    toast.querySelector('button').addEventListener('click', () => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-10px)';
                        setTimeout(() => toast.remove(), 250);
                    });
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-10px)';
                        setTimeout(() => toast.remove(), 250);
                    }, 4000);
                }
                window.showToast = showToast;

                document.addEventListener('DOMContentLoaded', () => {
                    initIcons();
                });
            })();
        </script>
    </body>
</html>
