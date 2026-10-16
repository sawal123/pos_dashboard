<x-layouts::auth.pos :title="__('Autentikasi Dua Faktor')">
    <div
        class="space-y-6"
        x-data="{
            showRecovery: @js($errors->has('recovery_code')),
            digits: ['', '', '', '', '', ''],
            get fullCode() {
                return this.digits.join('');
            },
            handleInput(index, event) {
                let val = event.target.value.replace(/[^0-9]/g, '');

                // If user pasted or browser autofilled multiple digits into one box
                if (val.length > 1) {
                    this.applyCode(val);
                    return;
                }

                this.digits[index] = val;

                if (val && index < 5) {
                    this.$nextTick(() => {
                        this.$refs['digit' + (index + 1)]?.focus();
                        this.$refs['digit' + (index + 1)]?.select();
                    });
                }
            },
            handleKeydown(index, event) {
                if (event.key === 'Backspace') {
                    if (!this.digits[index] && index > 0) {
                        event.preventDefault();
                        this.digits[index - 1] = '';
                        this.$nextTick(() => {
                            this.$refs['digit' + (index - 1)]?.focus();
                        });
                    }
                } else if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    this.$refs['digit' + (index - 1)]?.focus();
                } else if (event.key === 'ArrowRight' && index < 5) {
                    event.preventDefault();
                    this.$refs['digit' + (index + 1)]?.focus();
                }
            },
            handlePaste(event) {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text').trim();
                const cleaned = pasted.replace(/[^0-9]/g, '');
                if (cleaned) {
                    this.applyCode(cleaned);
                }
            },
            applyCode(codeStr) {
                for (let i = 0; i < 6; i++) {
                    this.digits[i] = codeStr[i] || '';
                }
                const targetIdx = Math.min(codeStr.length, 5);
                this.$nextTick(() => {
                    this.$refs['digit' + targetIdx]?.focus();
                });
            },
            toggleMode() {
                this.showRecovery = !this.showRecovery;
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    if (this.showRecovery) {
                        this.$refs.recoveryInput?.focus();
                    } else {
                        this.$refs.digit0?.focus();
                    }
                });
            },
            init() {
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    if (!this.showRecovery) {
                        this.$refs.digit0?.focus();
                    } else {
                        this.$refs.recoveryInput?.focus();
                    }
                });
            }
        }"
    >
        {{-- ==================== HEADER ==================== --}}
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                <span x-show="!showRecovery">{{ __('Autentikasi Dua Faktor') }}</span>
                <span x-show="showRecovery" x-cloak>{{ __('Pemulihan Akun') }}</span>
            </span>

            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-show="!showRecovery">
                {{ __('Verifikasi Identitas Anda') }}
            </h1>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-show="showRecovery" x-cloak>
                {{ __('Gunakan Kode Pemulihan') }}
            </h1>

            <p class="text-sm text-slate-500 dark:text-slate-400" x-show="!showRecovery">
                {{ __('Masukkan 6 digit kode keamanan yang tampil pada aplikasi authenticator Anda.') }}
            </p>
            <p class="text-sm text-slate-500 dark:text-slate-400" x-show="showRecovery" x-cloak>
                {{ __('Masukkan salah satu kode pemulihan darurat yang Anda simpan saat mengaktifkan 2FA.') }}
            </p>
        </div>

        {{-- ==================== ERROR ALERT ==================== --}}
        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 p-4 flex items-start gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5"></i>
                <div class="text-xs text-rose-800 dark:text-rose-300 space-y-1">
                    @foreach ($errors->all() as $error)
                        <p class="font-medium">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ==================== FORM ==================== --}}
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5" x-ref="twoFactorForm">
            @csrf

            {{-- OTP 6 DIGITS MODE --}}
            <div x-show="!showRecovery" class="space-y-3">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 text-center">
                    {{ __('Kode Autentikasi (6 Digit)') }}
                </label>

                <div
                    class="flex items-center justify-center gap-2 sm:gap-2.5"
                    @paste="handlePaste($event)"
                >
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            x-ref="digit{{ $i }}"
                            :value="digits[{{ $i }}]"
                            @input="handleInput({{ $i }}, $event)"
                            @keydown="handleKeydown({{ $i }}, $event)"
                            class="w-11 h-13 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-xs selection:bg-indigo-500 selection:text-white"
                            aria-label="{{ __('Digit :num', ['num' => $i + 1]) }}"
                            autocomplete="off"
                        >
                    @endfor
                </div>

                {{-- Hidden input synced with digits, submitted to Fortify --}}
                <input
                    type="hidden"
                    name="code"
                    id="twoFactorCode"
                    :value="fullCode"
                    :disabled="showRecovery"
                >
            </div>

            {{-- RECOVERY CODE MODE --}}
            <div x-show="showRecovery" x-cloak class="space-y-2">
                <label for="recovery_code" class="block text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ __('Kode Pemulihan Darurat') }}
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <i data-lucide="key" class="w-4 h-4"></i>
                    </span>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        x-ref="recoveryInput"
                        :disabled="!showRecovery"
                        autocomplete="one-time-code"
                        placeholder="xxxxx-xxxxx"
                        class="w-full h-11 pl-10 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono tracking-wider text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    >
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    {{ __('Tip: Kode pemulihan dapat digunakan satu kali jika Anda kehilangan akses ke perangkat autentikator.') }}
                </p>
            </div>

            {{-- SUBMIT BUTTON --}}
            <button
                type="submit"
                data-test="confirm-two-factor-button"
                class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 cursor-pointer"
            >
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                <span>{{ __('Lanjutkan Masuk') }}</span>
            </button>

            {{-- TOGGLE MODE --}}
            <div class="pt-1 text-center">
                <button
                    type="button"
                    @click="toggleMode()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:underline cursor-pointer focus:outline-none"
                >
                    <i data-lucide="key-round" class="w-3.5 h-3.5" x-show="!showRecovery"></i>
                    <i data-lucide="smartphone" class="w-3.5 h-3.5" x-show="showRecovery" x-cloak></i>
                    <span x-show="!showRecovery">{{ __('Gunakan kode pemulihan darurat') }}</span>
                    <span x-show="showRecovery" x-cloak>{{ __('Gunakan kode aplikasi autentikator') }}</span>
                </button>
            </div>
        </form>

        {{-- ==================== BACK TO LOGIN ==================== --}}
        <div class="border-t border-slate-100 dark:border-slate-800 pt-4 text-center">
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
            >
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>{{ __('Batal dan kembali ke halaman masuk') }}</span>
            </a>
        </div>
    </div>
</x-layouts::auth.pos>
