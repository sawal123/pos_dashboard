@props(['roleOptions' => []])

{{-- DASH-10B2 — owner-only role change (member <-> cashier) confirmation modal. --}}
<div
    id="changeRoleModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="changeRoleModalTitle"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70" data-close-change-role-modal></div>

    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="px-5 py-4 border-b border-slate-200/80 dark:border-slate-800">
                <h2 id="changeRoleModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                    Ubah Peran Anggota
                </h2>
            </div>

            <form id="changeRoleForm" method="POST" action="#" class="p-5 space-y-4">
                @csrf
                @method('PATCH')

                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Ubah peran
                    <span id="changeRoleName" class="font-semibold text-slate-900 dark:text-white">anggota ini</span>
                    pada bisnis aktif. Peran baru berlaku pada permintaan berikutnya.
                </p>

                <fieldset class="space-y-2">
                    <legend class="sr-only">Pilih peran baru</legend>
                    @foreach($roleOptions as $option)
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer">
                            <input
                                type="radio"
                                name="role"
                                value="{{ $option['value'] }}"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                required
                            >
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </fieldset>

                @error('role')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
                @error('member')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                    <button
                        type="button"
                        data-close-change-role-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <i data-lucide="user-cog" class="w-4 h-4"></i>
                        Simpan Peran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
