@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')

        {{-- Apply the persisted NexaPOS theme before paint to avoid a flash. --}}
        <script>
            (function () {
                try {
                    if (localStorage.getItem('nexa-theme') === 'dark') {
                        document.documentElement.classList.add('dark');
                        document.documentElement.classList.remove('light');
                    }
                } catch (e) {}
            })();
        </script>

        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased transition-colors duration-300">

        <div class="flex min-h-screen flex-col lg:flex-row">

            {{-- ==================== BRAND PANEL ==================== --}}
            <aside class="relative hidden overflow-hidden bg-slate-900 lg:flex lg:w-[45%] xl:w-1/2 flex-col justify-between p-10 xl:p-14 text-white">
                <div class="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-indigo-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-24 -right-16 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>

                <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-2.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 rounded-xl">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/40 shrink-0">
                        <i data-lucide="zap" class="h-5 w-5"></i>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight">
                        Nexa<span class="text-indigo-400">POS</span>
                    </span>
                </a>

                <div class="relative z-10 max-w-md space-y-6">
                    <h2 class="text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight">
                        Kelola bisnis Anda dalam satu dashboard.
                    </h2>
                    <p class="text-sm xl:text-base text-slate-300 leading-relaxed">
                        Pantau penjualan, stok, kas, dan perangkat dari satu tempat — dirancang untuk operasional
                        toko yang tetap berjalan meski koneksi terputus.
                    </p>

                    <ul class="space-y-4 pt-2">
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/5 border border-white/10 text-indigo-300">
                                <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold">Ringkasan penjualan</p>
                                <p class="text-xs text-slate-400">Lihat performa harian dan tren omzet per periode.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/5 border border-white/10 text-indigo-300">
                                <i data-lucide="package" class="h-4 w-4"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold">Produk, layanan & stok</p>
                                <p class="text-xs text-slate-400">Kelola katalog dan peringatan stok dalam satu menu.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/5 border border-white/10 text-indigo-300">
                                <i data-lucide="wallet" class="h-4 w-4"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold">Kas & pengeluaran</p>
                                <p class="text-xs text-slate-400">Catat arus kas masuk dan keluar secara rapi.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="relative z-10 text-xs text-slate-500">
                    &copy; {{ date('Y') }} NexaPOS. Semua data terpisah per bisnis.
                </p>
            </aside>

            {{-- ==================== FORM PANEL ==================== --}}
            <main class="relative flex flex-1 items-center justify-center px-4 py-10 sm:px-8">
                {{-- Theme toggle --}}
                <button
                    type="button"
                    id="themeToggleBtn"
                    class="absolute right-4 top-4 p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Ganti mode tampilan tema"
                    data-tooltip="Mode Tampilan"
                >
                    <i data-lucide="moon" class="w-5 h-5 hidden dark:inline" id="moonIcon"></i>
                    <i data-lucide="sun" class="w-5 h-5 inline dark:hidden" id="sunIcon"></i>
                </button>

                <div class="w-full max-w-md">
                    {{-- Brand (mobile / tablet) --}}
                    <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-2.5 lg:hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-xl">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/30 shrink-0">
                            <i data-lucide="zap" class="h-5 w-5"></i>
                        </div>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            Nexa<span class="text-indigo-600 dark:text-indigo-400">POS</span>
                        </span>
                    </a>

                    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-6 sm:p-8">
                        {{ $slot }}
                    </div>

                    <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-500">
                        Dengan masuk, Anda menyetujui ketentuan penggunaan NexaPOS.
                    </p>
                </div>
            </main>
        </div>

        {{-- ==================== TOAST CONTAINER ==================== --}}
        <div id="toastContainer" class="fixed top-4 right-4 z-[100] space-y-3 w-[calc(100vw-2rem)] max-w-sm pointer-events-none"></div>

        @fluxScripts

        <script>
            // ========== LUCIDE ICONS ==========
            function initIcons() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
            initIcons();
            document.addEventListener('DOMContentLoaded', initIcons);
            document.addEventListener('livewire:navigated', initIcons);

            // ========== THEME TOGGLE (shares the dashboard 'nexa-theme' key) ==========
            (function () {
                const htmlEl = document.documentElement;
                const themeToggleBtn = document.getElementById('themeToggleBtn');
                const moonIcon = document.getElementById('moonIcon');
                const sunIcon = document.getElementById('sunIcon');

                function applyTheme(theme) {
                    const isDark = theme === 'dark';
                    htmlEl.classList.toggle('dark', isDark);
                    htmlEl.classList.toggle('light', !isDark);
                    if (moonIcon) moonIcon.style.display = isDark ? 'inline' : 'none';
                    if (sunIcon) sunIcon.style.display = isDark ? 'none' : 'inline';
                    try {
                        localStorage.setItem('nexa-theme', theme);
                    } catch (e) {}
                }

                if (themeToggleBtn) {
                    themeToggleBtn.addEventListener('click', function () {
                        applyTheme(htmlEl.classList.contains('dark') ? 'light' : 'dark');
                    });
                }

                applyTheme(htmlEl.classList.contains('dark') ? 'dark' : 'light');
            })();
        </script>

        @stack('scripts')
    </body>
</html>
