@props(['summary'])

@php
    $cards = [
        ['id' => 'usersSummaryTotal', 'label' => 'Total Anggota', 'value' => $summary['total_members'] ?? 0, 'icon' => 'users', 'class' => 'text-slate-700 dark:text-slate-200'],
        ['id' => 'usersSummaryOwner', 'label' => 'Pemilik', 'value' => $summary['owner_count'] ?? 0, 'icon' => 'crown', 'class' => 'text-indigo-700 dark:text-indigo-300'],
        ['id' => 'usersSummaryMember', 'label' => 'Anggota', 'value' => $summary['member_count'] ?? 0, 'icon' => 'user', 'class' => 'text-slate-700 dark:text-slate-200'],
        ['id' => 'usersSummaryCashier', 'label' => 'Kasir', 'value' => $summary['cashier_count'] ?? 0, 'icon' => 'user-round-cog', 'class' => 'text-emerald-700 dark:text-emerald-300'],
        ['id' => 'usersSummaryOtherRole', 'label' => 'Peran Lain', 'value' => $summary['other_role_count'] ?? 0, 'icon' => 'shield-question', 'class' => 'text-amber-700 dark:text-amber-300'],
        ['id' => 'usersSummaryVerified', 'label' => 'Email Terverifikasi', 'value' => $summary['verified_count'] ?? 0, 'icon' => 'mail-check', 'class' => 'text-emerald-700 dark:text-emerald-300'],
        ['id' => 'usersSummaryUnverified', 'label' => 'Email Belum Terverifikasi', 'value' => $summary['unverified_count'] ?? 0, 'icon' => 'mail-warning', 'class' => 'text-rose-700 dark:text-rose-300'],
    ];
@endphp

<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
    @foreach($cards as $card)
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 truncate">{{ $card['label'] }}</p>
                    <p id="{{ $card['id'] }}" class="mt-1 text-2xl font-extrabold tabular-nums {{ $card['class'] }}">
                        {{ number_format((int) $card['value'], 0, ',', '.') }}
                    </p>
                </div>
                <div class="w-10 h-10 shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <i data-lucide="{{ $card['icon'] }}" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
    @endforeach
</section>
