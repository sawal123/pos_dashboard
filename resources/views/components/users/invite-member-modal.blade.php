{{-- DASH-10B1 — owner-only member invitation / direct registration modal. --}}
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
        <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl max-h-[92vh] flex flex-col overflow-hidden animate-scale-in">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 id="inviteMemberModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                            Tambah Pengguna / Kasir
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Pilih metode pendaftaran langsung atau undang via email.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    data-close-invite-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup formulir pendaftaran pengguna"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('users.invitations.store') }}" id="inviteMemberForm" class="p-5 space-y-4 overflow-y-auto flex-1">
                @csrf
                <input type="hidden" name="mode" id="inviteModeInput" value="{{ old('mode', 'direct') }}">

                {{-- Mode Switcher Tabs --}}
                <div class="p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 grid grid-cols-2 gap-1 text-xs font-bold">
                    <button
                        type="button"
                        id="tabDirectRegisterBtn"
                        onclick="setInviteModalMode('direct')"
                        class="py-2.5 px-3 rounded-lg text-center transition-all flex items-center justify-center gap-1.5 focus:outline-none"
                    >
                        <i data-lucide="user-check" class="w-3.5 h-3.5"></i>
                        <span>Daftarkan Langsung</span>
                    </button>
                    <button
                        type="button"
                        id="tabEmailInviteBtn"
                        onclick="setInviteModalMode('invite')"
                        class="py-2.5 px-3 rounded-lg text-center transition-all flex items-center justify-center gap-1.5 focus:outline-none"
                    >
                        <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                        <span>Kirim Undangan Email</span>
                    </button>
                </div>

                {{-- Name Field (Direct mode only) --}}
                <div id="directNameContainer" class="space-y-1.5">
                    <label for="inviteMemberName" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Nama Lengkap
                    </label>
                    <input
                        id="inviteMemberName"
                        name="name"
                        type="text"
                        maxlength="255"
                        value="{{ old('name') }}"
                        placeholder="Contoh: Budi Santoso / Kasir 1"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email Field (Always required) --}}
                <div class="space-y-1.5">
                    <label for="inviteMemberEmail" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Alamat Email
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
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password & Confirmation Fields (Direct mode only) --}}
                <div id="directPasswordContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label for="inviteMemberPassword" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Kata Sandi
                        </label>
                        <input
                            id="inviteMemberPassword"
                            name="password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('password')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="inviteMemberPasswordConfirmation" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Konfirmasi Kata Sandi
                        </label>
                        <input
                            id="inviteMemberPasswordConfirmation"
                            name="password_confirmation"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                {{-- Role Selection --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Pilih Peran
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-500/50 dark:hover:border-indigo-500/50 cursor-pointer bg-white dark:bg-slate-950 transition-colors">
                            <input type="radio" name="role" value="member" {{ old('role', 'member') === 'member' ? 'checked' : '' }} class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-900 dark:text-white">Anggota</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 leading-tight mt-0.5">Operasional & laporan dashboard</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-500/50 dark:hover:border-indigo-500/50 cursor-pointer bg-white dark:bg-slate-950 transition-colors">
                            <input type="radio" name="role" value="cashier" {{ old('role') === 'cashier' ? 'checked' : '' }} class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-900 dark:text-white">Kasir</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 leading-tight mt-0.5">Akses khusus POS kasir & sinkronisasi</span>
                            </div>
                        </label>
                    </div>
                    @error('role')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Contextual Explanation Card --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3">
                    <p id="inviteModalExplText" class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Akun langsung aktif dengan kata sandi yang Anda tentukan. Pengguna dapat langsung login ke POS Web atau POS Mobile tanpa perlu konfirmasi email.
                    </p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        data-close-invite-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="submitInviteBtn"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                    >
                        <i data-lucide="user-check" id="submitInviteIcon" class="w-4 h-4"></i>
                        <span id="submitInviteText">Daftarkan Pengguna</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setInviteModalMode(mode) {
        const modeInput = document.getElementById('inviteModeInput');
        const tabDirect = document.getElementById('tabDirectRegisterBtn');
        const tabInvite = document.getElementById('tabEmailInviteBtn');
        const directNameContainer = document.getElementById('directNameContainer');
        const directPasswordContainer = document.getElementById('directPasswordContainer');
        const nameInput = document.getElementById('inviteMemberName');
        const passInput = document.getElementById('inviteMemberPassword');
        const passConfInput = document.getElementById('inviteMemberPasswordConfirmation');
        const explText = document.getElementById('inviteModalExplText');
        const submitText = document.getElementById('submitInviteText');
        const submitIcon = document.getElementById('submitInviteIcon');

        if (!modeInput) return;

        modeInput.value = mode;

        if (mode === 'direct') {
            tabDirect.classList.add('bg-white', 'dark:bg-slate-900', 'text-indigo-600', 'dark:text-indigo-400', 'shadow-xs');
            tabDirect.classList.remove('text-slate-600', 'dark:text-slate-400');
            tabInvite.classList.remove('bg-white', 'dark:bg-slate-900', 'text-indigo-600', 'dark:text-indigo-400', 'shadow-xs');
            tabInvite.classList.add('text-slate-600', 'dark:text-slate-400');

            if (directNameContainer) directNameContainer.classList.remove('hidden');
            if (directPasswordContainer) directPasswordContainer.classList.remove('hidden');
            if (nameInput) {
                nameInput.required = true;
                nameInput.disabled = false;
            }
            if (passInput) {
                passInput.required = true;
                passInput.disabled = false;
            }
            if (passConfInput) {
                passConfInput.required = true;
                passConfInput.disabled = false;
            }

            if (explText) {
                explText.textContent = 'Akun langsung aktif dengan kata sandi yang Anda tentukan. Pengguna dapat langsung login ke POS Web atau POS Mobile tanpa perlu konfirmasi email.';
            }
            if (submitText) submitText.textContent = 'Daftarkan Pengguna';
            if (submitIcon) submitIcon.setAttribute('data-lucide', 'user-check');
        } else {
            tabInvite.classList.add('bg-white', 'dark:bg-slate-900', 'text-indigo-600', 'dark:text-indigo-400', 'shadow-xs');
            tabInvite.classList.remove('text-slate-600', 'dark:text-slate-400');
            tabDirect.classList.remove('bg-white', 'dark:bg-slate-900', 'text-indigo-600', 'dark:text-indigo-400', 'shadow-xs');
            tabDirect.classList.add('text-slate-600', 'dark:text-slate-400');

            if (directNameContainer) directNameContainer.classList.add('hidden');
            if (directPasswordContainer) directPasswordContainer.classList.add('hidden');
            if (nameInput) {
                nameInput.required = false;
                nameInput.disabled = true;
            }
            if (passInput) {
                passInput.required = false;
                passInput.disabled = true;
            }
            if (passConfInput) {
                passConfInput.required = false;
                passConfInput.disabled = true;
            }

            if (explText) {
                explText.textContent = 'Email berisi tautan undangan akan dikirim ke calon anggota agar mereka menentukan kata sandi sendiri.';
            }
            if (submitText) submitText.textContent = 'Kirim Undangan';
            if (submitIcon) submitIcon.setAttribute('data-lucide', 'send');
        }

        if (typeof window.initIcons === 'function') {
            window.initIcons();
        }
    }

    (function() {
        const initialMode = "{{ old('mode', 'direct') }}";
        // Run immediately or on next microtask
        setTimeout(function() {
            setInviteModalMode(initialMode);
        }, 10);
    })();
</script>
