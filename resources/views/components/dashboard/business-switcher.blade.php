@props([
    'businesses' => null,
    'currentBusiness' => null,
])

@php
    $businesses = $businesses ?? $dashboardBusinesses ?? collect();
    $currentBusiness = $currentBusiness ?? $dashboardCurrentBusiness ?? null;
    $count = $businesses->count();
@endphp

<div class="px-3 pt-3 pb-2 border-b border-slate-200/80 dark:border-slate-800">
    {{-- Full Mode (visible when sidebar is expanded or in mobile drawer) --}}
    <div class="sidebar-business-card">
        @if($count === 0 || ! $currentBusiness)
            {{-- No Business State --}}
            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 text-xs">
                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-medium">
                    <i data-lucide="building-2" class="w-4 h-4 shrink-0 text-slate-400"></i>
                    <span class="truncate">Belum Ada Bisnis</span>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 leading-normal">
                    Bisnis yang dapat Anda akses akan muncul di sini.
                </p>
            </div>
        @elseif($count === 1)
            {{-- Single Business Context Pill/Card --}}
            <div 
                class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800/80"
                title="{{ $currentBusiness->name }}"
            >
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <i data-lucide="store" class="w-3.5 h-3.5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <span class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 block font-semibold">Bisnis Aktif</span>
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
                        {{ $currentBusiness->name }}
                    </span>
                </div>
            </div>
        @else
            {{-- Multiple Businesses: Accessible Native Select Form --}}
            <form 
                method="POST" 
                action="{{ route('dashboard.business-context.update') }}" 
                class="space-y-1"
            >
                @csrf
                <div class="flex items-center justify-between gap-1 mb-1">
                    <label 
                        for="dashboard-business-switcher-select" 
                        class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold block"
                    >
                        Bisnis Aktif
                    </label>
                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">
                        {{ $count }} Bisnis
                    </span>
                </div>
                <div class="relative">
                    <select
                        id="dashboard-business-switcher-select"
                        name="business_id"
                        onchange="this.form.submit()"
                        class="w-full pl-2.5 pr-8 py-1.5 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors truncate cursor-pointer appearance-none"
                        aria-label="Pilih Bisnis Aktif"
                    >
                        @foreach($businesses as $b)
                            <option value="{{ $b->id }}" @selected($currentBusiness->id === $b->id)>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
                        <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
            </form>
        @endif
    </div>

    {{-- Collapsed Mode Mini Icon (visible when .sidebar-collapsed is active) --}}
    <div class="sidebar-business-mini hidden justify-center py-1">
        <div 
            class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300"
            data-tooltip-right="{{ $currentBusiness ? $currentBusiness->name : 'Belum Ada Bisnis' }}"
            aria-label="{{ $currentBusiness ? 'Bisnis: ' . $currentBusiness->name : 'Belum Ada Bisnis' }}"
        >
            <i data-lucide="{{ $currentBusiness ? 'store' : 'building-2' }}" class="w-4 h-4"></i>
        </div>
    </div>
</div>
