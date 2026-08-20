{{-- ==================== NAVBAR / TOPBAR ==================== --}}
<header id="navbar" class="sticky top-0 z-30 h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 md:px-6">

    {{-- Left: Hamburger + Breadcrumb --}}
    <div class="flex items-center gap-3">
        <button id="hamburgerBtn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors" aria-label="Open sidebar">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <button id="desktopCollapseBtn" class="hidden md:flex p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors" aria-label="Collapse sidebar">
            <i data-lucide="panel-left" class="w-5 h-5"></i>
        </button>
        <nav class="flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
            <span class="text-slate-400 dark:text-slate-500 hidden sm:inline">Home</span>
            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 dark:text-slate-500 hidden sm:inline"></i>
            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $title ?? 'Dashboard' }}</span>
        </nav>
    </div>

    {{-- Right: Actions --}}
    <div class="flex items-center gap-1.5 md:gap-2">

        {{-- Search Button --}}
        <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" aria-label="Search" data-tooltip="Search">
            <i data-lucide="search" class="w-5 h-5"></i>
        </button>

        {{-- Notification Dropdown --}}
        <div class="relative">
            <button id="notificationBtn" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors relative" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-tooltip="Notifications">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 border-2 border-white dark:border-slate-900"></span>
            </button>
            <div id="notificationDropdown" class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-80 md:w-96 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg shadow-slate-200/50 dark:shadow-slate-900/50 overflow-hidden z-50">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="font-semibold text-sm text-slate-900 dark:text-white">Notifications</h3>
                    <span class="text-xs text-indigo-600 dark:text-indigo-400 font-medium cursor-pointer hover:underline">Mark all read</span>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center flex-shrink-0"><i data-lucide="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400"></i></div>
                            <div><p class="text-sm text-slate-800 dark:text-slate-200 font-medium">Sync completed</p><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">All data has been synchronized.</p><p class="text-xs text-slate-400 dark:text-slate-500 mt-1">5 min ago</p></div>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center flex-shrink-0"><i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i></div>
                            <div><p class="text-sm text-slate-800 dark:text-slate-200 font-medium">12 pending records</p><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Some transactions need attention.</p><p class="text-xs text-slate-400 dark:text-slate-500 mt-1">25 min ago</p></div>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center flex-shrink-0"><i data-lucide="info" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i></div>
                            <div><p class="text-sm text-slate-800 dark:text-slate-200 font-medium">New product added</p><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">"Beverages Pack" was added to catalog.</p><p class="text-xs text-slate-400 dark:text-slate-500 mt-1">1 hour ago</p></div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-center"><span class="text-xs text-indigo-600 dark:text-indigo-400 font-medium cursor-pointer hover:underline">View all notifications</span></div>
            </div>
        </div>

        {{-- Theme Toggle --}}
        <button id="themeToggleBtn" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" aria-label="Toggle theme" data-tooltip="Toggle theme">
            <i data-lucide="moon" class="w-5 h-5 hidden dark:inline" id="moonIcon"></i>
            <i data-lucide="sun" class="w-5 h-5 inline dark:hidden" id="sunIcon"></i>
        </button>

        {{-- Cloud Badge --}}
        <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-800/50 text-indigo-600 dark:text-indigo-400 text-xs font-semibold">
            <i data-lucide="cloud" class="w-3.5 h-3.5"></i>
            <span class="hidden lg:inline">CLOUD</span>
        </span>

        {{-- User Dropdown --}}
        <div class="relative ml-1">
            <button id="profileDropdownBtn" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-haspopup="true" aria-expanded="false">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                    {{ auth()->check() ? auth()->user()->initials() : 'AL' }}
                </div>
                <div class="hidden lg:block text-left">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 leading-tight">{{ auth()->check() ? auth()->user()->name : 'Alex Lee' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight">Admin</p>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 dark:text-slate-500 hidden lg:inline"></i>
            </button>
            <div id="profileDropdown" class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-52 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg shadow-slate-200/50 dark:shadow-slate-900/50 overflow-hidden z-50">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->check() ? auth()->user()->name : 'Alex Lee' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->check() ? auth()->user()->email : 'alex@nexapos.id' }}</p>
                </div>
                <a href="{{ route('profile.edit') }}" wire:navigate class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors"><i data-lucide="user" class="w-4 h-4 text-slate-400"></i> Profile</a>
                <a href="{{ route('profile.edit') }}" wire:navigate class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors"><i data-lucide="settings" class="w-4 h-4 text-slate-400"></i> Account Settings</a>
                <div class="border-t border-slate-200 dark:border-slate-700"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
