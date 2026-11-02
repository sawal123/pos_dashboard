{{-- ==================== PWA STATUS & INSTALL BANNER ==================== --}}
<div id="pwaContainer" class="select-none pointer-events-none" aria-live="polite">

    {{-- 1. Real-time Offline / Online Network Indicator --}}
    <div
        id="pwaNetworkBanner"
        class="fixed top-3 left-1/2 -translate-x-1/2 z-[9999] pointer-events-auto transition-all duration-300 transform -translate-y-20 opacity-0 max-w-md w-[calc(100%-2rem)]"
    >
        <div id="pwaNetworkBannerContent" class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl shadow-xl backdrop-blur-md border text-xs font-semibold">
            <div class="flex items-center gap-2.5 min-w-0">
                <span id="pwaNetworkIcon" class="w-2 h-2 rounded-full shrink-0"></span>
                <span id="pwaNetworkText" class="truncate"></span>
            </div>
            <button
                type="button"
                id="pwaNetworkRetryBtn"
                onclick="window.location.reload()"
                class="hidden shrink-0 px-2 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-900 dark:text-amber-100 border border-amber-500/30 transition-colors"
            >
                Coba Lagi
            </button>
        </div>
    </div>

    {{-- 2. PWA Update Available Banner --}}
    <div
        id="pwaUpdateBanner"
        class="fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-[9990] pointer-events-auto transition-all duration-300 transform translate-y-24 opacity-0 max-w-sm w-[calc(100%-2rem)]"
    >
        <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-2xl bg-slate-900/95 dark:bg-slate-800/95 text-white border border-indigo-500/40 shadow-2xl backdrop-blur-md text-xs">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                    <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>
                </div>
                <div>
                    <p class="font-bold">Pembaruan Tersedia</p>
                    <p class="text-[11px] text-slate-300">Versi baru NexaPOS siap digunakan.</p>
                </div>
            </div>
            <button
                type="button"
                id="pwaReloadBtn"
                class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-bold text-white shadow-sm transition-all text-[11px] shrink-0"
            >
                Perbarui
            </button>
        </div>
    </div>

    {{-- 3. PWA Install App Floating Prompt --}}
    <div
        id="pwaInstallCard"
        class="fixed bottom-20 md:bottom-6 right-4 md:right-6 z-[9980] pointer-events-auto transition-all duration-300 transform translate-y-32 opacity-0 max-w-xs w-[calc(100%-2rem)] sm:w-80"
    >
        <div class="p-3.5 rounded-2xl bg-white/95 dark:bg-slate-900/95 text-slate-900 dark:text-white border border-slate-200 dark:border-slate-800 shadow-2xl backdrop-blur-md">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/20 shrink-0">
                        <i data-lucide="zap" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-xs">Install Aplikasi NexaPOS</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Akses cepat & layar penuh</p>
                    </div>
                </div>
                <button
                    type="button"
                    id="pwaDismissInstallBtn"
                    class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    aria-label="Tutup saran instalasi"
                >
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>
            <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-3 leading-relaxed">
                Pasang NexaPOS di perangkat Anda untuk performa lebih cepat, mode kasir layar penuh, dan akses offline.
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="pwaPromptInstallBtn"
                    class="flex-1 py-1.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-1.5"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                    <span>Pasang Sekarang</span>
                </button>
                <button
                    type="button"
                    id="pwaLaterInstallBtn"
                    class="py-1.5 px-3 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold text-xs transition-colors"
                >
                    Nanti
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // -------------------------------------------------------------
    // PWA STATE & HANDLERS
    // -------------------------------------------------------------
    let deferredInstallPrompt = null;
    let newWorker = null;

    const networkBanner = document.getElementById('pwaNetworkBanner');
    const networkContent = document.getElementById('pwaNetworkBannerContent');
    const networkIcon = document.getElementById('pwaNetworkIcon');
    const networkText = document.getElementById('pwaNetworkText');
    const networkRetryBtn = document.getElementById('pwaNetworkRetryBtn');

    const installCard = document.getElementById('pwaInstallCard');
    const promptInstallBtn = document.getElementById('pwaPromptInstallBtn');
    const dismissInstallBtn = document.getElementById('pwaDismissInstallBtn');
    const laterInstallBtn = document.getElementById('pwaLaterInstallBtn');

    const updateBanner = document.getElementById('pwaUpdateBanner');
    const reloadBtn = document.getElementById('pwaReloadBtn');

    // Check if app is running in standalone mode (already installed)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                         window.navigator.standalone === true;

    // -------------------------------------------------------------
    // 1. NETWORK STATUS INDICATOR
    // -------------------------------------------------------------
    let networkTimer = null;

    function updateNetworkStatus(isOnline) {
        if (!networkBanner || !networkContent || !networkIcon || !networkText) return;

        if (networkTimer) clearTimeout(networkTimer);

        if (!isOnline) {
            // OFFLINE MODE
            networkContent.className = 'flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl shadow-xl backdrop-blur-md border text-xs font-semibold bg-amber-500/95 dark:bg-amber-950/90 text-amber-950 dark:text-amber-100 border-amber-600/40 dark:border-amber-700/60';
            networkIcon.className = 'w-2.5 h-2.5 rounded-full bg-amber-600 dark:bg-amber-400 animate-pulse shrink-0';
            networkText.textContent = 'Mode Offline Aktif — Koneksi terputus. Data tersimpan & kasir lokal tetap berfungsi.';
            if (networkRetryBtn) networkRetryBtn.classList.remove('hidden');

            networkBanner.classList.remove('-translate-y-20', 'opacity-0');
            networkBanner.classList.add('translate-y-0', 'opacity-100');
        } else {
            // ONLINE RESTORED
            networkContent.className = 'flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl shadow-xl backdrop-blur-md border text-xs font-semibold bg-emerald-600/95 dark:bg-emerald-950/90 text-white dark:text-emerald-100 border-emerald-500/40 dark:border-emerald-700/60';
            networkIcon.className = 'w-2.5 h-2.5 rounded-full bg-emerald-300 shrink-0';
            networkText.textContent = 'Terhubung Kembali — Jaringan internet aktif.';
            if (networkRetryBtn) networkRetryBtn.classList.add('hidden');

            networkBanner.classList.remove('-translate-y-20', 'opacity-0');
            networkBanner.classList.add('translate-y-0', 'opacity-100');

            // Auto dismiss after 3.5 seconds
            networkTimer = setTimeout(() => {
                networkBanner.classList.remove('translate-y-0', 'opacity-100');
                networkBanner.classList.add('-translate-y-20', 'opacity-0');
            }, 3500);
        }
    }

    // Initialize network status check
    if (!navigator.onLine) {
        updateNetworkStatus(false);
    }
    window.addEventListener('offline', () => updateNetworkStatus(false));
    window.addEventListener('online', () => updateNetworkStatus(true));

    // -------------------------------------------------------------
    // 2. INSTALL PROMPT HANDLING
    // -------------------------------------------------------------
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent default mini-infobar
        e.preventDefault();
        deferredInstallPrompt = e;
        window.pwaDeferredPrompt = e;

        // If standalone or dismissed recently in this session, do not show card
        if (isStandalone || sessionStorage.getItem('pwa_prompt_dismissed') === 'true') {
            return;
        }

        // Show card after 3 seconds of navigation
        setTimeout(() => {
            if (installCard && deferredInstallPrompt && !isStandalone) {
                installCard.classList.remove('translate-y-32', 'opacity-0');
                installCard.classList.add('translate-y-0', 'opacity-100');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }, 3000);
    });

    function hideInstallCard(remember = true) {
        if (!installCard) return;
        installCard.classList.remove('translate-y-0', 'opacity-100');
        installCard.classList.add('translate-y-32', 'opacity-0');
        if (remember) {
            sessionStorage.setItem('pwa_prompt_dismissed', 'true');
        }
    }

    if (promptInstallBtn) {
        promptInstallBtn.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;
            hideInstallCard(true);
            deferredInstallPrompt.prompt();
            const choiceResult = await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            window.pwaDeferredPrompt = null;
        });
    }

    if (dismissInstallBtn) dismissInstallBtn.addEventListener('click', () => hideInstallCard(true));
    if (laterInstallBtn) laterInstallBtn.addEventListener('click', () => hideInstallCard(true));

    window.addEventListener('appinstalled', () => {
        hideInstallCard(true);
        deferredInstallPrompt = null;
        window.pwaDeferredPrompt = null;
        console.log('[PWA] NexaPOS installed successfully');
    });

    // Global install trigger function for custom UI buttons
    window.triggerPwaInstall = async function() {
        if (window.pwaDeferredPrompt) {
            window.pwaDeferredPrompt.prompt();
            const choiceResult = await window.pwaDeferredPrompt.userChoice;
            window.pwaDeferredPrompt = null;
            hideInstallCard(true);
            return choiceResult.outcome;
        } else {
            alert('Aplikasi NexaPOS dapat dipasang melalui menu browser (ikon Install/Pasang Aplikasi di address bar).');
            return null;
        }
    };

    // -------------------------------------------------------------
    // 3. SERVICE WORKER UPDATE NOTIFICATION
    // -------------------------------------------------------------
    window.addEventListener('pwa-update-available', () => {
        if (updateBanner) {
            updateBanner.classList.remove('translate-y-24', 'opacity-0');
            updateBanner.classList.add('translate-y-0', 'opacity-100');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });

    if (reloadBtn) {
        reloadBtn.addEventListener('click', () => {
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
            }
            window.location.reload();
        });
    }
})();
</script>
