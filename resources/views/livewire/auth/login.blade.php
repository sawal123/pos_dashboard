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

        {{-- ==================== PASSKEY ==================== --}}
        <x-passkey-verify />

        {{-- ==================== LOGIN FORM ==================== --}}
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
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
                class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 disabled:opacity-60 disabled:pointer-events-none"
            >
                <i data-lucide="log-in" class="w-4 h-4"></i>
                <span>{{ __('Masuk') }}</span>
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
</x-layouts::auth.pos>
