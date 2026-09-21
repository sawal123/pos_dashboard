<div 
    id="sync-detail-drawer" 
    class="fixed inset-0 z-50 overflow-hidden hidden" 
    role="dialog" 
    aria-modal="true" 
    aria-labelledby="sync-drawer-title"
>
    {{-- Backdrop --}}
    <div id="sync-drawer-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity duration-300 opacity-0"></div>

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div 
            id="sync-drawer-panel"
            class="w-screen max-w-md sm:max-w-lg bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 flex flex-col transform transition-transform duration-300 translate-x-full"
        >
            {{-- Header --}}
            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 id="sync-drawer-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        Detail Push Request
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Informasi request sinkronisasi tercatat
                    </p>
                </div>
                <button 
                    type="button" 
                    id="sync-drawer-close" 
                    class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup drawer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                {{-- Info banner --}}
                <div class="bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl p-4 flex gap-3 text-xs text-indigo-800 dark:text-indigo-300">
                    <svg class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>
                        Record ini menunjukkan push request yang telah tercatat oleh server.
                    </p>
                </div>

                {{-- Detail List --}}
                <div class="space-y-4">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block mb-1">Request ID</span>
                        <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl p-3">
                            <p id="drawer-sync-request-id" class="font-mono text-xs text-slate-900 dark:text-slate-100 font-semibold break-all select-all">
                                -
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-xs font-medium text-slate-400 block mb-1">Diproses Pada</span>
                            <p id="drawer-sync-processed-at" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                -
                            </p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-slate-400 block mb-1">Outlet</span>
                            <p id="drawer-sync-outlet" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                -
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            Informasi Perangkat
                        </h3>
                        <div>
                            <span class="text-xs font-medium text-slate-400 block mb-1">Nama Perangkat</span>
                            <p id="drawer-sync-device-name" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                -
                            </p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-slate-400 block mb-1">Identifier Perangkat</span>
                            <p id="drawer-sync-device-identifier" class="font-mono text-xs text-slate-600 dark:text-slate-300 break-all select-all bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                -
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/60 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                <button 
                    type="button" 
                    id="sync-drawer-footer-close" 
                    class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
