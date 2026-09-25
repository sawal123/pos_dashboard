{{-- DASH-10B1 — owner-only remove-member confirmation modal. --}}
<div
    id="removeMemberModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="removeMemberModalTitle"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70" data-close-remove-member-modal></div>

    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="px-5 py-4 border-b border-slate-200/80 dark:border-slate-800">
                <h2 id="removeMemberModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                    Hapus Anggota
                </h2>
            </div>

            <form id="removeMemberForm" method="POST" action="#" class="p-5 space-y-4">
                @csrf
                @method('DELETE')

                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Hapus
                    <span id="removeMemberName" class="font-semibold text-slate-900 dark:text-white">anggota ini</span>
                    dari bisnis aktif? Akses dashboard dan sinkronisasi ke bisnis ini akan langsung ditolak.
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Akun pengguna tidak dihapus. Keanggotaan pada bisnis lain tetap utuh.
                </p>

                @error('member')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                    <button
                        type="button"
                        data-close-remove-member-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                    >
                        <i data-lucide="user-minus" class="w-4 h-4"></i>
                        Hapus Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
