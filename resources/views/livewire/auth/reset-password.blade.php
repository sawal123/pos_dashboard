<x-layouts::auth.pos :title="__('Atur Ulang Kata Sandi')">
    <div class="space-y-6">
        {{-- ==================== HEADER ==================== --}}
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                Keamanan Akun
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Buat kata sandi baru') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Masukkan kata sandi baru untuk akun Anda.') }}
            </p>
        </div>

        {{-- ==================== SESSION STATUS ==================== --}}
        @if (session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        {{-- ==================== RESET PASSWORD FORM ==================== --}}
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ submitting: false, showPass: false, showConf: false }" @submit="submitting = true">
            @csrf

            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

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
                        autocomplete="email"
                        value="{{ old('email', request('email')) }}"
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
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('Kata Sandi Baru') }}
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        x-bind:type="showPass ? 'text' : 'password'"
                        required
                        autocomplete="new-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        class="w-full h-11 pl-10 pr-11 rounded-xl border bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors {{ $errors->has('password') ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }}"
                    >
                    <button
                        type="button"
                        x-on:click="showPass = !showPass"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg"
                        aria-label="{{ __('Tampilkan atau sembunyikan kata sandi') }}"
                    >
                        <i data-lucide="eye" class="w-4 h-4" x-show="!showPass"></i>
                        <i data-lucide="eye-off" class="w-4 h-4" x-show="showPass" x-cloak></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Password Confirmation --}}
            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('Konfirmasi Kata Sandi Baru') }}
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        x-bind:type="showConf ? 'text' : 'password'"
                        required
                        autocomplete="new-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        class="w-full h-11 pl-10 pr-11 rounded-xl border bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors {{ $errors->has('password_confirmation') ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }}"
                    >
                    <button
                        type="button"
                        x-on:click="showConf = !showConf"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg"
                        aria-label="{{ __('Tampilkan atau sembunyikan konfirmasi kata sandi') }}"
                    >
                        <i data-lucide="eye" class="w-4 h-4" x-show="!showConf"></i>
                        <i data-lucide="eye-off" class="w-4 h-4" x-show="showConf" x-cloak></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="mt-1.5 flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Submit Button --}}
            <button
                type="submit"
                x-bind:disabled="submitting"
                data-test="reset-password-button"
                class="w-full h-11 flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
                <span x-show="!submitting" class="flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    {{ __('Simpan Kata Sandi Baru') }}
                </span>
                <span x-show="submitting" x-cloak class="flex items-center gap-2">
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                    {{ __('Menyimpan...') }}
                </span>
            </button>
        </form>

        {{-- Return to Login --}}
        <div class="pt-2 text-center">
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                wire:navigate
            >
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                {{ __('Kembali ke halaman masuk') }}
            </a>
        </div>
    </div>
</x-layouts::auth.pos>
