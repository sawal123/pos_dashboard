<x-layouts::auth.pos :title="__('Masuk')">
    <div class="space-y-6">
        {{-- ==================== HEADER ==================== --}}
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                Akses Terlindungi
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Masuk ke Akun Anda') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Masukkan email dan kata sandi untuk melanjutkan ke dashboard.') }}
            </p>
        </div>

        {{-- ==================== SESSION STATUS ==================== --}}
        @if (session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        {{-- ==================== GOOGLE SIGN-IN ==================== --}}
        @php($googleClientId = trim((string) config('services.google.client_id')))
        @if ($googleClientId !== '')
            <div class="space-y-4">
                <form id="google-login-form" method="POST" action="{{ route('auth.google.login') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="credential" id="google-credential">
                </form>

                <div id="google-signin" class="flex justify-center" data-client-id="{{ $googleClientId }}"></div>

                @error('google')
                    <p class="text-xs text-center text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
                @error('credential')
                    <p class="text-xs text-center text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="relative my-5">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-200 dark:border-slate-700"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase tracking-wider">
                        <span class="px-2 text-slate-400 dark:text-slate-500 bg-white dark:bg-slate-900">
                            {{ __('Atau masuk dengan email') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== PASSKEY ==================== --}}
        <x-passkey-verify />

        {{-- ==================== LOGIN FORM ==================== --}}
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            {{-- Email Address --}}
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('Email') }}
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </span>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        value="{{ old('email') }}"
                        placeholder="nama@bisnis.com"
                        class="w-full h-11 pl-10 pr-3 rounded-xl border bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors {{ $errors->has('email') ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }}"
                    >
                </div>
                @error('email')
                    <p class="mt-1.5 flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Password --}}
            <div x-data="{ show: false }">
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ __('Kata Sandi') }}
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ __('Lupa kata sandi?') }}
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        x-bind:type="show ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        class="w-full h-11 pl-10 pr-11 rounded-xl border bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors {{ $errors->has('password') ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }}"
                    >
                    <button
                        type="button"
                        x-on:click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg"
                        aria-label="{{ __('Tampilkan atau sembunyikan kata sandi') }}"
                    >
                        <i data-lucide="eye" class="w-4 h-4" x-show="!show"></i>
                        <i data-lucide="eye-off" class="w-4 h-4" x-show="show" x-cloak></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Remember Me --}}
            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="custom-checkbox">
                <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('Ingat saya') }}</span>
            </label>

            {{-- Submit --}}
            <button
                type="submit"
                data-test="login-button"
                :disabled="submitting"
                class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
            >
                {{-- Spin Loader --}}
                <svg
                    x-show="submitting"
                    x-cloak
                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <i data-lucide="log-in" class="w-4 h-4" x-show="!submitting"></i>
                <span x-show="!submitting">{{ __('Masuk') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Memproses...') }}</span>
            </button>
        </form>

        {{-- ==================== REGISTER LINK ==================== --}}
        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            {{ __('Belum punya akun?') }}
            <a href="{{ route('register') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ __('Daftar sekarang') }}
            </a>
        </p>
    </div>

    @if ($googleClientId !== '')
        @push('scripts')
            <script src="https://accounts.google.com/gsi/client" async defer onload="initNexaGoogleSignIn()"></script>
            <script>
                function initNexaGoogleSignIn() {
                    const container = document.getElementById('google-signin');
                    if (!container || typeof google === 'undefined' || !google.accounts?.id) {
                        return;
                    }

                    google.accounts.id.initialize({
                        client_id: container.dataset.clientId,
                        callback: function (response) {
                            document.getElementById('google-credential').value = response.credential;
                            document.getElementById('google-login-form').submit();
                        },
                    });

                    google.accounts.id.renderButton(container, {
                        type: 'standard',
                        theme: 'outline',
                        size: 'large',
                        text: 'continue_with',
                        shape: 'pill',
                        logo_alignment: 'left',
                        width: Math.max(200, Math.min(400, container.clientWidth || 360)),
                    });
                }
            </script>
        @endpush
    @endif
</x-layouts::auth.pos>
