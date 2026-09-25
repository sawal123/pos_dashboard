{{-- DASH-10B1 — owner-only invite modal. Role is fixed to `member`. --}}
<div
    id="inviteMemberModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="inviteMemberModalTitle"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70" data-close-invite-modal></div>

    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800">
                <h2 id="inviteMemberModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                    Undang Anggota
                </h2>
                <button
                    type="button"
                    data-close-invite-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup formulir undangan"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('users.invitations.store') }}" class="p-5 space-y-4">
                @csrf

                <div>
                    <label for="inviteMemberEmail" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                        Email calon anggota
                    </label>
                    <input
                        id="inviteMemberEmail"
                        name="email"
                        type="email"
                        required
                        maxlength="255"
                        value="{{ old('email') }}"
                        placeholder="nama@example.com"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <input type="hidden" name="role" value="member">

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3">
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Undangan dikirim sebagai peran <span class="font-semibold">Anggota</span>.
                        Peran <span class="font-semibold">Pemilik</span> dan
                        <span class="font-semibold">Kasir</span> belum tersedia untuk diundang.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                    <button
                        type="button"
                        data-close-invite-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Kirim Undangan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
