<div
    id="userDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="userDrawerTitle"
>
    <div
        id="userDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    <div
        id="userDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div class="min-w-0">
                <span id="userDrawerSubtitle" class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider font-mono truncate">
                    -
                </span>
                <h2 id="userDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight break-words">
                    -
                </h2>
            </div>
            <button
                type="button"
                data-close-user-drawer
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail pengguna"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5">
            <div id="userDrawerLoading" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Memuat detail pengguna...
            </div>

            <div id="userDrawerError" class="hidden rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30 p-4 text-sm text-rose-700 dark:text-rose-300">
                Detail pengguna tidak dapat dimuat.
            </div>

            <div id="userDrawerContent" class="hidden space-y-5 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="userDrawerRole" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    <span id="userDrawerVerification" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Nama</span>
                        <span id="userDrawerName" class="font-semibold text-slate-900 dark:text-white break-words">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Email</span>
                        <span id="userDrawerEmail" class="font-semibold text-slate-900 dark:text-white break-all">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Bergabung dengan Bisnis Aktif</span>
                        <span id="userDrawerJoinedAt" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Akun Dibuat</span>
                        <span id="userDrawerAccountCreatedAt" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Detail ini hanya menampilkan keanggotaan pada bisnis aktif. Data sensitif akun tidak ditampilkan.
                </p>
            </div>
        </div>

        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                data-close-user-drawer
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
