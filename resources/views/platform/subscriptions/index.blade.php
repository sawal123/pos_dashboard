<x-layouts::platform :title="'Langganan'">
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
                    Manajemen Langganan &amp; Paket
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Daftar seluruh subscription lintas bisnis, status masa aktif, serta hak akses entitlement Cloud.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($subscriptions->total()) }}</strong> Langganan</span>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.subscriptions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                {{-- Search query input --}}
                <div class="lg:col-span-4 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari bisnis, slug, atau owner..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Cari langganan"
                    />
                </div>

                {{-- Plan Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="plan"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter paket"
                    >
                        <option value="">Semua Paket</option>
                        <option value="cloud" {{ $filters['plan'] === 'cloud' ? 'selected' : '' }}>Cloud</option>
                        <option value="free" {{ $filters['plan'] === 'free' ? 'selected' : '' }}>Free Tier</option>
                    </select>
                </div>

                {{-- Status Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="status"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter status"
                    >
                        <option value="">Semua Status</option>
                        <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                        <option value="expired" {{ $filters['status'] === 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>

                {{-- Entitlement Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="entitlement"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter hak akses cloud"
                    >
                        <option value="">Semua Entitlement</option>
                        <option value="granted" {{ $filters['entitlement'] === 'granted' ? 'selected' : '' }}>Cloud Granted</option>
                        <option value="denied" {{ $filters['entitlement'] === 'denied' ? 'selected' : '' }}>Cloud Denied</option>
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
                    @if($filters['q'] || $filters['plan'] || $filters['status'] || $filters['entitlement'])
                        <a
                            href="{{ route('platform.subscriptions.index') }}"
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
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400" aria-label="Daftar Langganan Platform">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 select-none">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 md:px-6">Bisnis</th>
                            <th scope="col" class="py-3.5 px-4">Paket</th>
                            <th scope="col" class="py-3.5 px-4">Status</th>
                            <th scope="col" class="py-3.5 px-4">Cloud Entitlement</th>
                            <th scope="col" class="py-3.5 px-4">Mulai</th>
                            <th scope="col" class="py-3.5 px-4">Berakhir</th>
                            <th scope="col" class="py-3.5 px-4">Sisa Masa Aktif</th>
                            <th scope="col" class="py-3.5 px-4">Updated At</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($subscriptions as $subscription)
                            @php
                                $hasCloud = $subscription->hasCloudAccess();
                                $isCloud = $subscription->isCloud();
                                $expiresAt = $subscription->expires_at;
                                $isExpired = $subscription->isExpired();
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Bisnis --}}
                                <td class="py-3.5 px-4 md:px-6">
                                    @if($subscription->business)
                                        <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                            <a href="{{ route('platform.businesses.show', $subscription->business) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                                                {{ $subscription->business->name }}
                                            </a>
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-xs">
                                            {{ $subscription->business->slug }}
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Bisnis tidak ditemukan</span>
                                    @endif
                                </td>

                                {{-- Paket --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold {{ $isCloud ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                        @if($isCloud)
                                            <i data-lucide="cloud" class="w-3 h-3 text-indigo-600 dark:text-indigo-400"></i>
                                            Cloud
                                        @else
                                            <i data-lucide="box" class="w-3 h-3 text-slate-400"></i>
                                            Free
                                        @endif
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td class="py-3.5 px-4">
                                    @if($subscription->status === \App\Models\Subscription::STATUS_ACTIVE)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @elseif($subscription->status === \App\Models\Subscription::STATUS_EXPIRED)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Cloud Entitlement --}}
                                <td class="py-3.5 px-4">
                                    @if($hasCloud)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                            Granted
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            <i data-lucide="shield-x" class="w-3.5 h-3.5 text-slate-400"></i>
                                            Denied
                                        </span>
                                    @endif
                                </td>

                                {{-- Mulai --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $subscription->starts_at ? $subscription->starts_at->format('d M Y') : '-' }}
                                </td>

                                {{-- Berakhir --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $expiresAt ? $expiresAt->format('d M Y') : 'Tidak ditentukan' }}
                                </td>

                                {{-- Sisa Masa Aktif --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($expiresAt === null)
                                        @if($hasCloud)
                                            <span class="text-indigo-600 dark:text-indigo-400 font-medium">Tanpa batas waktu</span>
                                        @else
                                            <span class="text-slate-400 italic">-</span>
                                        @endif
                                    @elseif($expiresAt->isPast())
                                        <span class="text-rose-600 dark:text-rose-400 font-bold">Kadaluarsa</span>
                                    @else
                                        @php
                                            $daysLeft = now()->diffInDays($expiresAt, false);
                                        @endphp
                                        @if($daysLeft > 0)
                                            <span class="text-slate-700 dark:text-slate-300 font-semibold">Sisa {{ $daysLeft }} hari</span>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400 font-bold">Hari ini</span>
                                        @endif
                                    @endif
                                </td>

                                {{-- Updated At --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $subscription->updated_at ? $subscription->updated_at->format('d M Y') : '-' }}
                                </td>

                                {{-- Aksi Detail --}}
                                <td class="py-3.5 px-4 text-right">
                                    <a
                                        href="{{ route('platform.subscriptions.show', $subscription) }}"
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
                                        @if($filters['q'] || $filters['plan'] || $filters['status'] || $filters['entitlement'])
                                            Tidak ada langganan yang cocok dengan filter pencarian.
                                        @else
                                            Belum ada langganan terdaftar di sistem.
                                        @endif
                                    </p>
                                    @if($filters['q'] || $filters['plan'] || $filters['status'] || $filters['entitlement'])
                                        <div class="mt-2">
                                            <a href="{{ route('platform.subscriptions.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
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
            @if($subscriptions->hasPages())
                <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                    {{ $subscriptions->links() }}
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
