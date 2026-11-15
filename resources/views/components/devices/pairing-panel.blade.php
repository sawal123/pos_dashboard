{{-- PAIR-01 — hasil pembuatan kode pairing + daftar kode yang masih aktif. --}}
@props([
    'codes' => [],
    'canManage' => false,
])

@php
    $issued = session('pairing_code');
@endphp

@if($issued || count($codes) > 0)
    <section class="space-y-4" data-pairing-panel>
        @if($issued)
            <div
                class="rounded-2xl border border-indigo-200/80 dark:border-indigo-900/60 bg-indigo-50/70 dark:bg-indigo-950/30 p-4 sm:p-5"
                role="status"
                data-pairing-result
                data-expires-at="{{ $issued['expires_at'] }}"
            >
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-500 dark:text-indigo-300">Kode pairing kasir</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            Outlet: <span class="font-semibold">{{ $issued['outlet'] ?? '-' }}</span>
                            · berlaku {{ \App\Models\DevicePairingCode::TTL_MINUTES }} menit
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <p
                            class="font-mono text-3xl sm:text-4xl font-extrabold tracking-[0.35em] text-indigo-700 dark:text-indigo-200"
                            data-pairing-code
                            aria-label="Kode pairing 4 digit"
                        >{{ $issued['code'] }}</p>
                        <button
                            type="button"
                            data-copy-pairing-code="{{ $issued['code'] }}"
                            class="p-2 rounded-xl border border-indigo-200 dark:border-indigo-800/70 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100/70 dark:hover:bg-indigo-900/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            aria-label="Salin kode pairing"
                        >
                            <i data-lucide="copy" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <ol class="mt-4 grid gap-1.5 text-xs text-slate-600 dark:text-slate-300 list-decimal pl-5">
                    <li>Berikan kode ini kepada kasir (bukan dibagikan ke publik).</li>
                    <li>Kasir membuka aplikasi POS → Sinkronisasi → <span class="font-semibold">Pasangkan Perangkat</span>.</li>
                    <li>Kode hanya bisa dipakai satu kali. Sisa waktu:
                        <span class="font-semibold tabular-nums" data-pairing-countdown>menghitung…</span>
                    </li>
                </ol>

                @if($canManage && $issued['id'])
                    <form method="POST" action="{{ route('devices.pairing-code.revoke', ['codeId' => $issued['id']]) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-rose-200 dark:border-rose-900/60 text-xs font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors"
                        >
                            <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                            <span>Batalkan kode ini</span>
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if(count($codes) > 0)
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Kode pairing aktif</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Kode yang belum dipakai kasir. Membuat kode baru otomatis membatalkan kode lama pada outlet yang sama.</p>
                </div>
                <ul class="divide-y divide-slate-200/70 dark:divide-slate-800">
                    @foreach($codes as $code)
                        <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 py-3" data-active-pairing-code data-expires-at="{{ $code->expires_at->toIso8601String() }}">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $code->outlet?->name ?? 'Outlet #'.$code->outlet_id }}
                                </p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Dibuat {{ $code->created_at?->diffForHumans() }} · sisa
                                    <span class="font-semibold tabular-nums" data-pairing-remaining>—</span>
                                </p>
                            </div>
                            @if($canManage)
                                <form method="POST" action="{{ route('devices.pairing-code.revoke', ['codeId' => $code->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        <i data-lucide="x" class="w-3 h-3"></i>
                                        <span>Batalkan</span>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
