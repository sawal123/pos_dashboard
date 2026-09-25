@php
    $user = auth()->user();
    $userName = $user ? $user->name : 'Alex Lee';
    $userEmail = $user ? $user->email : 'alex@nexapos.id';
    // DASH-10B2 — show the caller's real role in the active business.
    $activeRole = $dashboardBusinessRole ?? null;
    $userRole = is_string($activeRole) && $activeRole !== ''
        ? \App\Models\Business::roleLabel($activeRole)
        : 'Tanpa Bisnis Aktif';
    $initials = ($user && method_exists($user, 'initials')) ? $user->initials() : 'AL';
@endphp

<div class="relative">
    <button
        type="button"
        id="profileDropdownBtn"
        class="flex items-center gap-2.5 p-1 sm:p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="Menu Pengguna: {{ $userName }}"
    >
        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center text-xs font-bold shadow-xs shrink-0">
            {{ $initials }}
        </div>
        <div class="hidden lg:block text-left">
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 leading-tight truncate max-w-[120px]">
                {{ $userName }}
            </p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">
                {{ $userRole }}
            </p>
        </div>
        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 hidden lg:inline shrink-0"></i>
    </button>

    {{-- Dropdown Panel --}}
    <div
        id="profileDropdown"
        class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 rounded-2xl shadow-xl shadow-slate-200/40 dark:shadow-slate-950/60 overflow-hidden z-50 py-1"
        role="menu"
        aria-orientation="vertical"
        aria-labelledby="profileDropdownBtn"
    >
        {{-- User Header Info --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $userName }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $userEmail }}</p>
            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold">
                {{ $userRole }}
            </span>
        </div>

        {{-- Navigation Options --}}
        <div class="py-1">
            <a
                href="{{ Route::has('profile.edit') ? route('profile.edit') : '#' }}"
                wire:navigate
                class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                role="menuitem"
            >
                <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                <span>Profil</span>
            </a>

            <a
                href="{{ Route::has('profile.edit') ? route('profile.edit') : '#' }}"
                wire:navigate
                class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                role="menuitem"
            >
                <i data-lucide="settings" class="w-4 h-4 text-slate-400"></i>
                <span>Pengaturan</span>
            </a>
        </div>

        {{-- Divider & Logout --}}
        <div class="border-t border-slate-100 dark:border-slate-700/80 my-1"></div>

        @if(Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                @csrf
                <button
                    type="submit"
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50/70 dark:hover:bg-rose-950/30 transition-colors text-left"
                    role="menuitem"
                >
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Keluar</span>
                </button>
            </form>
        @else
            <button
                type="button"
                onclick="showToast('info', 'Autentikasi logout belum terhubung.')"
                class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50/70 dark:hover:bg-rose-950/30 transition-colors text-left"
                role="menuitem"
            >
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>Keluar</span>
            </button>
        @endif
    </div>
</div>
