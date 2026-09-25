@props(['users'])

@php
    $roleBadgeClass = function (string $category): string {
        return match ($category) {
            'owner' => 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            'member' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
    $verifyBadgeClass = fn (bool $verified): string => $verified
        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
@endphp

<div class="lg:hidden space-y-3">
    @foreach($users as $user)
        <article class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-extrabold text-slate-900 dark:text-white break-words">{{ $user['name'] }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 break-all">{{ $user['email'] }}</p>
                </div>
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $roleBadgeClass($user['role_category']) }}">
                    {{ $user['role'] }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $verifyBadgeClass($user['is_verified']) }}">
                    {{ $user['is_verified'] ? 'Terverifikasi' : 'Belum Terverifikasi' }}
                </span>
                <span class="text-slate-500 dark:text-slate-400">
                    Bergabung {{ $user['joined_at'] ?? '-' }}
                </span>
            </div>

            <button
                type="button"
                class="view-user-detail-btn w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-detail-url="{{ route('users.detail', $user['id']) }}"
            >
                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                Lihat Detail
            </button>

            @if(($user['role_raw'] ?? '') === 'member')
                <button
                    type="button"
                    data-remove-member
                    data-remove-url="{{ route('users.members.destroy', $user['id']) }}"
                    data-member-name="{{ $user['name'] }}"
                    class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                >
                    <i data-lucide="user-minus" class="w-4 h-4"></i>
                    Hapus Anggota
                </button>
            @endif
        </article>
    @endforeach
</div>
