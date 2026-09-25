@props(['filterOptions', 'currentFilters'])

<form
    action="{{ route('users.index') }}"
    method="GET"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4 space-y-4"
>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label for="userSearch" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Nama atau Email
            </label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input
                    id="userSearch"
                    name="q"
                    type="search"
                    value="{{ $currentFilters['q'] ?? '' }}"
                    placeholder="Cari nama atau email anggota"
                    class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>
        </div>

        <div>
            <label for="userRoleFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Peran
            </label>
            <select
                id="userRoleFilter"
                name="role"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all">Semua Peran</option>
                @foreach(($filterOptions['roles'] ?? []) as $role)
                    <option value="{{ $role['value'] }}" @selected(($currentFilters['role'] ?? 'all') === $role['value'])>
                        {{ $role['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="userVerificationFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Verifikasi Email
            </label>
            <select
                id="userVerificationFilter"
                name="verification"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all" @selected(($currentFilters['verification'] ?? 'all') === 'all')>Semua Status</option>
                <option value="verified" @selected(($currentFilters['verification'] ?? 'all') === 'verified')>Terverifikasi</option>
                <option value="unverified" @selected(($currentFilters['verification'] ?? 'all') === 'unverified')>Belum Terverifikasi</option>
            </select>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-end">
        <a
            href="{{ route('users.index') }}"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            Reset
        </a>
        <button
            type="submit"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="filter" class="w-4 h-4"></i>
            Terapkan
        </button>
    </div>
</form>
