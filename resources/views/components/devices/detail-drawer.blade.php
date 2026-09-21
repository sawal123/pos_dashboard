{{-- ==================== DEVICE DETAIL DRAWER ==================== --}}
<div
    id="deviceDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="deviceDrawerTitle"
>
    {{-- Backdrop --}}
    <div
        id="deviceDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="deviceDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div>
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Informasi Terminal
                </span>
                <h2 id="deviceDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                    -
                </h2>
            </div>
            <button
                type="button"
                id="closeDeviceDrawerBtn"
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail perangkat"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Drawer Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs sm:text-sm">

            {{-- Status Hero Card --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between gap-3">
                <div>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Status Operasional</span>
                    <h3 id="deviceDrawerNameHeading" class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white">-</h3>
                </div>
                <div id="deviceDrawerStatusBadge">
                    {{-- Dynamically populated badge --}}
                </div>
            </div>

            {{-- Attributes List --}}
            <div class="space-y-3">
                <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Spesifikasi & Relasi
                </h4>
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 overflow-hidden text-xs">
                    {{-- Identifier --}}
                    <div class="p-3 bg-white dark:bg-slate-900 space-y-1">
                        <span class="text-slate-500 dark:text-slate-400 block">Device Identifier</span>
                        <span id="deviceDrawerIdentifier" class="font-mono text-slate-900 dark:text-white break-all select-all font-semibold block">-</span>
                    </div>
                    {{-- Outlet --}}
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Outlet Terpasang</span>
                        <span id="deviceDrawerOutlet" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    {{-- Platform --}}
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Platform OS</span>
                        <span id="deviceDrawerPlatform" class="font-medium text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    {{-- Registered At --}}
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Waktu Terdaftar</span>
                        <span id="deviceDrawerRegisteredAt" class="font-mono text-slate-700 dark:text-slate-300">-</span>
                    </div>
                    {{-- Last Seen At --}}
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Terakhir Terlihat</span>
                        <span id="deviceDrawerLastSeenAt" class="font-mono text-slate-700 dark:text-slate-300">-</span>
                    </div>
                </div>
            </div>

            {{-- Notes Section (hidden if notes is null) --}}
            <div id="deviceDrawerNotesContainer" class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-1">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Catatan Perangkat</span>
                <p id="deviceDrawerNotes" class="text-xs text-slate-700 dark:text-slate-300 italic">-</p>
            </div>

        </div>

        {{-- Drawer Footer --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                id="closeDeviceDrawerFooterBtn"
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
