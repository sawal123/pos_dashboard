@props(['invitations', 'invitationSummary'])

@php
    $statusBadgeClass = fn (string $status): string => match ($status) {
        'pending' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        'accepted' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'revoked' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    };

    $buckets = [
        ['key' => 'pending', 'label' => 'Menunggu'],
        ['key' => 'accepted', 'label' => 'Diterima'],
        ['key' => 'expired', 'label' => 'Kedaluwarsa'],
        ['key' => 'revoked', 'label' => 'Dibatalkan'],
    ];
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-4 border-b border-slate-200/80 dark:border-slate-800">
        <div>
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Undangan Anggota</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Undangan email yang bisa menunggu, diterima, kedaluwarsa, atau dibatalkan.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($buckets as $bucket)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[11px] font-bold {{ $statusBadgeClass($bucket['key']) }}">
                    {{ $bucket['label'] }}
                    <span class="tabular-nums">{{ $invitationSummary[$bucket['key']] ?? 0 }}</span>
                </span>
            @endforeach
        </div>
    </header>

    @if($invitations->isEmpty())
        <div class="px-4 py-10 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Belum ada undangan. Gunakan tombol “Undang Anggota” untuk mengundang anggota baru.
            </p>
        </div>
    @else
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($invitations as $invitation)
                <li class="px-4 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-slate-900 dark:text-white break-all">{{ $invitation['email'] }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold {{ $statusBadgeClass($invitation['status']) }}">
                                {{ $invitation['status_label'] }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                                {{ $invitation['role'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Dikirim {{ $invitation['created_at'] ?? '-' }}
                            @if($invitation['invited_by'] !== '')
                                oleh {{ $invitation['invited_by'] }}
                            @endif
                            · Berlaku sampai {{ $invitation['expires_at'] ?? '-' }}
                        </p>
                    </div>

                    @if($invitation['is_pending'])
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('users.invitations.resend', $invitation['id']) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                >
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                    Kirim ulang
                                </button>
                            </form>
                            <form method="POST" action="{{ route('users.invitations.revoke', $invitation['id']) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                                >
                                    <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                    Batalkan
                                </button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
