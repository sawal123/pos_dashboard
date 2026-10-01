<x-layouts::platform :title="'Bisnis'">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Manajemen Bisnis / Merchant
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Daftar global seluruh entitas bisnis yang terdaftar dalam ekosistem platform NexaPOS.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="building-2" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($businesses->total()) }}</strong> Bisnis</span>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.businesses.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                {{-- Search query input --}}
                <div class="lg:col-span-5 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari nama bisnis, slug, atau owner..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Cari bisnis"
                    />
                </div>

                {{-- Status Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="status"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter status"
                    >
                        <option value="">Semua Status</option>
                        <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>

                {{-- Plan Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="plan"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter paket langganan"
                    >
                        <option value="">Semua Paket</option>
                        <option value="free" {{ $filters['plan'] === 'free' ? 'selected' : '' }}>Free Tier</option>
                        <option value="cloud" {{ $filters['plan'] === 'cloud' ? 'selected' : '' }}>Cloud / Premium</option>
                    </select>
                </div>

                {{-- Actions --}}
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        Filter
                    </button>
                    @if($filters['q'] || $filters['status'] || $filters['plan'])
                        <a
                            href="{{ route('platform.businesses.index') }}"
                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs font-semibold transition-colors"
                            title="Reset Filter"
                        >
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Container --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400" aria-label="Daftar Bisnis Platform">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 select-none">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 md:px-6">Nama Bisnis & Slug</th>
                            <th scope="col" class="py-3.5 px-4">Status</th>
                            <th scope="col" class="py-3.5 px-4">Owner</th>
                            <th scope="col" class="py-3.5 px-3 text-center">Outlet</th>
                            <th scope="col" class="py-3.5 px-3 text-center">User</th>
                            <th scope="col" class="py-3.5 px-3 text-center">Perangkat</th>
                            <th scope="col" class="py-3.5 px-4">Paket</th>
                            <th scope="col" class="py-3.5 px-4">Terdaftar</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($businesses as $business)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Nama & Slug --}}
                                <td class="py-3.5 px-4 md:px-6">
                                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                        <a href="{{ route('platform.businesses.show', $business) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                                            {{ $business->name }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-xs">
                                        {{ $business->slug }}
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $business->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $business->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        {{ $business->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>

                                {{-- Owner --}}
                                <td class="py-3.5 px-4">
                                    @php
                                        $primaryOwner = $business->owners->first();
                                        $ownerCount = $business->owners->count();
                                    @endphp
                                    @if($primaryOwner)
                                        <div class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[160px]">
                                            {{ $primaryOwner->name }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[160px]">
                                            {{ $primaryOwner->email }}
                                        </div>
                                        @if($ownerCount > 1)
                                            <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                +{{ $ownerCount - 1 }} owner lain
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">Tanpa Owner</span>
                                    @endif
                                </td>

                                {{-- Outlet --}}
                                <td class="py-3.5 px-3 text-center">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($business->outlets_count) }}
                                    </span>
                                </td>

                                {{-- User --}}
                                <td class="py-3.5 px-3 text-center">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($business->users_count) }}
                                    </span>
                                </td>

                                {{-- Perangkat --}}
                                <td class="py-3.5 px-3 text-center">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($business->devices_count) }}
                                    </span>
                                </td>

                                {{-- Paket Langganan --}}
                                <td class="py-3.5 px-4">
                                    @if($business->subscription)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $business->subscription->isCloud() ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                                            {{ ucfirst($business->subscription->plan) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">Free Tier</span>
                                    @endif
                                </td>

                                {{-- Terdaftar --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $business->created_at ? $business->created_at->format('d M Y') : '-' }}
                                </td>

                                {{-- Aksi --}}
                                <td class="py-3.5 px-4 text-right">
                                    <a
                                        href="{{ route('platform.businesses.show', $business) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/60 text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition-colors"
                                    >
                                        <span>Detail</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 px-4 text-center">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="inbox" class="w-6 h-6"></i>
                                    </div>
                                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
                                        @if($filters['q'] || $filters['status'] || $filters['plan'])
                                            Tidak ada bisnis yang cocok dengan filter pencarian.
                                        @else
                                            Belum ada bisnis terdaftar di database.
                                        @endif
                                    </p>
                                    @if($filters['q'] || $filters['status'] || $filters['plan'])
                                        <div class="mt-2">
                                            <a href="{{ route('platform.businesses.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                                Reset filter pencarian
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            @if($businesses->hasPages())
                <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                    {{ $businesses->links() }}
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
